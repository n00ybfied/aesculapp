<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\{Appointment,AppointmentAvailability,AppointmentCancellationNotice,AppointmentResource,AppointmentType,User};use App\Repository\TenantMembershipRepository;use App\Service\{ActiveTenantProvider,AppointmentBlocker,AppointmentStaffNotifier,AppointmentCustomerNotifier};use Doctrine\DBAL\LockMode;use Doctrine\ORM\EntityManagerInterface;use Symfony\Bundle\SecurityBundle\Security;use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};use Symfony\Component\Routing\Attribute\Route;
final class ApiAppointmentController{
 public function __construct(private readonly EntityManagerInterface $em,private readonly ActiveTenantProvider $tenant,private readonly TenantMembershipRepository $memberships,private readonly Security $security,private readonly AppointmentBlocker $blocker,private readonly ?AppointmentStaffNotifier $staffNotifier=null,private readonly ?AppointmentCustomerNotifier $customerNotifier=null){}
 #[Route('/api/v1/appointments/types',methods:['GET'])] public function types():JsonResponse{return new JsonResponse(['types'=>array_map(fn(AppointmentType $t)=>$this->type($t),$this->em->getRepository(AppointmentType::class)->findBy(['tenant'=>$this->tenant->get(),'isVisible'=>true])),'bookingWindowDays'=>$this->tenant->get()->getAppointmentBookingFutureDays()]);}
    #[Route('/api/v1/appointments/slots', methods: ['GET'])]
    public function slots(Request $request): JsonResponse
    {
        $type = $this->typeFor((int) $request->query->get('typeId'));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date'));
        if (!$type || !$date) return new JsonResponse(['message' => 'Ungültige Auswahl.'], 422);
        $slots = $this->slotsFor($type, $date);
        $customer = $this->customer();
        if ($customer !== null) {
            $nearby = $this->em->createQueryBuilder()
                ->select('appointment')
                ->from(Appointment::class, 'appointment')
                ->where('appointment.tenant = :tenant AND appointment.customer = :customer AND appointment.type = :type')
                ->andWhere('appointment.status IN (:statuses)')
                ->andWhere('appointment.startsAt > :from AND appointment.startsAt < :until')
                ->setParameter('tenant', $this->tenant->get())
                ->setParameter('customer', $customer)
                ->setParameter('type', $type)
                ->setParameter('statuses', ['reserved', 'pending_staff_confirmation'])
                ->setParameter('from', $date->modify('-7 days'))
                ->setParameter('until', $date->modify('+8 days'))
                ->getQuery()
                ->getResult();
            $slots = array_values(array_filter($slots, static function (array $slot) use ($nearby): bool {
                $start = new \DateTimeImmutable($slot['startsAt']);
                foreach ($nearby as $appointment) {
                    if ($appointment->getStartsAt() > $start->modify('-7 days')
                        && $appointment->getStartsAt() < $start->modify('+7 days')) return false;
                }
                return true;
            }));
        }
        foreach ($slots as &$slot) {
            $resource = $this->em->getRepository(AppointmentResource::class)->find($slot['resourceId']);
            $slot['resourceName'] = $this->tenant->get()->showsAppointmentStaffNames() ? $resource?->getName() : null;
            $slot['requiresConfirmation'] = $this->tenant->get()->isAppointmentStaffConfirmationEnabled()
                && $resource !== null && $this->canConfirm($resource);
        }
        return new JsonResponse(['slots' => $slots]);
    }

    private function canConfirm(AppointmentResource $resource): bool
    {
        $user = $resource->getAssignedUser();
        $membership = $user === null ? null : $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null
            && [] !== array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles());
    }
 #[Route('/api/v1/appointments',methods:['POST'])]
 public function book(Request $request): JsonResponse
 {
    $user = $this->customer();
    if (!$user) return new JsonResponse(['message' => 'Unauthorized.'], 401);
    $data = $request->toArray();
    $type = $this->typeFor((int) ($data['typeId'] ?? 0));
    $start = isset($data['startsAt']) ? new \DateTimeImmutable((string) $data['startsAt']) : null;
    if (!$type || !$start || $start < new \DateTimeImmutable() || $start > new \DateTimeImmutable('+'.$this->tenant->get()->getAppointmentBookingFutureDays().' days')) {
        return new JsonResponse(['message' => 'Ungültiger Termin.'], 422);
    }
    foreach ($this->slotsFor($type, $start->setTime(0, 0)) as $slot) {
        if ($slot['startsAt'] !== $start->format(DATE_ATOM)) continue;
        $resource = $this->em->getRepository(AppointmentResource::class)->find($slot['resourceId']);
        if (!$resource) continue;
        $createdAppointment = null;
        $response = $this->em->wrapInTransaction(function () use ($resource, $type, $user, $start, $data, &$createdAppointment): JsonResponse {
            $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
            if ($membership === null) return new JsonResponse(['message' => 'Unauthorized.'], 401);
            $this->em->lock($membership, LockMode::PESSIMISTIC_WRITE);
            if ($this->hasSameTypeAppointmentWithinWeek($user, $type, $start)) {
                return new JsonResponse([
                    'code' => 'appointment_type_week_limit',
                    'message' => 'Zwischen zwei Terminen derselben Art müssen mindestens sieben Tage liegen.',
                ], 409);
            }
            $this->em->lock($resource, LockMode::PESSIMISTIC_WRITE);
            if (!$this->isFree($resource, $start, $type)) return new JsonResponse(['message' => 'Dieser Slot wurde soeben vergeben.'], 409);
            $end = $start->modify('+'.$type->getDurationMinutes().' minutes');
            $appointment = new Appointment($this->tenant->get(), $resource, $type, $user, $start, $end, substr(trim((string) ($data['note'] ?? '')), 0, 1000) ?: null);
            if ($this->tenant->get()->isAppointmentStaffConfirmationEnabled() && $this->canConfirm($resource)) $appointment->awaitStaffConfirmation();
            $this->em->persist($appointment);
            $this->em->flush();
            $createdAppointment = $appointment;
            return new JsonResponse(['appointment' => $this->appointment($appointment)], 201);
        });
        if ($createdAppointment instanceof Appointment) $this->staffNotifier?->notify($createdAppointment);
        if ($createdAppointment instanceof Appointment && $createdAppointment->getStatus() === 'pending_staff_confirmation') $this->customerNotifier?->pending($createdAppointment);
        return $response;
    }
    return new JsonResponse(['message' => 'Termin nicht verfügbar.'], 409);
 }
 private function hasSameTypeAppointmentWithinWeek(User $customer, AppointmentType $type, \DateTimeImmutable $start): bool
 {
    return (int) $this->em->createQueryBuilder()
        ->select('COUNT(appointment.id)')
        ->from(Appointment::class, 'appointment')
        ->where('appointment.tenant = :tenant AND appointment.customer = :customer AND appointment.type = :type')
        ->andWhere('appointment.status IN (:statuses)')
        ->andWhere('appointment.startsAt > :from AND appointment.startsAt < :until')
        ->setParameter('tenant', $this->tenant->get())
        ->setParameter('customer', $customer)
        ->setParameter('type', $type)
        ->setParameter('statuses', ['reserved', 'pending_staff_confirmation'])
        ->setParameter('from', $start->modify('-7 days'))
        ->setParameter('until', $start->modify('+7 days'))
        ->getQuery()
        ->getSingleScalarResult() > 0;
 }
 #[Route('/api/v1/appointments/mine',methods:['GET'])] public function mine():JsonResponse{$user=$this->customer();if(!$user)return new JsonResponse(['message'=>'Unauthorized.'],401);$items=$this->em->getRepository(Appointment::class)->findBy(['customer'=>$user],['startsAt'=>'ASC']);return new JsonResponse(['appointments'=>array_map(fn(Appointment $a)=>$this->appointment($a),$items)]);}
 #[Route('/api/v1/appointments/{id}/cancel',methods:['POST'])]
 public function cancel(int $id):JsonResponse
 {
  $user=$this->customer();
  $appointment=$user?$this->em->getRepository(Appointment::class)->findOneBy(['id'=>$id,'customer'=>$user,'tenant'=>$this->tenant->get()]):null;
  if(!$appointment instanceof Appointment)return new JsonResponse(['message'=>'Nicht gefunden.'],404);
  return $this->em->wrapInTransaction(function()use($appointment):JsonResponse{
   $this->em->lock($appointment,LockMode::PESSIMISTIC_WRITE);
   $this->em->refresh($appointment);
   if($appointment->getStatus()==='cancelled')return new JsonResponse(['appointment'=>$this->appointment($appointment)]);
   $cutoff = $appointment->getStatus() === 'pending_staff_confirmation'
    ? new \DateTimeImmutable()
    : new \DateTimeImmutable('+'.$this->tenant->get()->getAppointmentCancellationHours().' hours');
   if($appointment->getStartsAt()<$cutoff)return new JsonResponse(['message'=>'Bitte kontaktieren Sie die Apotheke zur Stornierung.'],422);
   $appointment->cancel();
   $this->em->persist(new AppointmentCancellationNotice($appointment));
   $this->em->flush();
   return new JsonResponse(['appointment'=>$this->appointment($appointment)]);
  });
 }
 #[Route('/api/v1/admin/appointments/types',methods:['GET','POST'])] public function adminTypes(Request $r):JsonResponse{if(!$this->admin())return new JsonResponse(['message'=>'Forbidden'],403);if($r->isMethod('GET'))return new JsonResponse(['types'=>array_map(fn(AppointmentType $t)=>$this->type($t),$this->em->getRepository(AppointmentType::class)->findBy(['tenant'=>$this->tenant->get()]))]);$d=$r->toArray();$duration=(int)($d['durationMinutes']??0);if(trim((string)($d['title']??''))===''||$duration<5)return new JsonResponse(['message'=>'Ungültige Terminart.'],422);$t=new AppointmentType($this->tenant->get(),trim($d['title']),trim((string)($d['description']??''))?:null,$duration,max(0,(int)($d['bufferMinutes']??5)));$this->em->persist($t);$this->em->flush();return new JsonResponse(['type'=>$this->type($t)],201);}
 #[Route('/api/v1/admin/appointments/resources',methods:['GET','POST'])]
 public function adminResources(Request $r):JsonResponse
 {
  if(!$this->admin())return new JsonResponse(['message'=>'Forbidden'],403);
  if($r->isMethod('GET'))return new JsonResponse(['resources'=>array_map(
   fn(AppointmentResource $resource)=>$this->resourceData($resource),
   $this->em->getRepository(AppointmentResource::class)->findBy(['tenant'=>$this->tenant->get(),'isActive'=>true]),
  )]);
  $data=$r->toArray();
  $name=trim((string)($data['name']??''));
  $color=$data['color']??null;
  $assignedUser=$this->staffUser($data['userId']??null);
  if($name===''||mb_strlen($name)>160||($color!==null&&(!is_string($color)||!preg_match('/^#[0-9a-fA-F]{6}$/',$color)))||($data['userId']??null)!==null&&$assignedUser===null)return new JsonResponse(['message'=>'Name, Farbe oder Mitarbeiterkonto ungültig.'],422);
  $resource=new AppointmentResource($this->tenant->get(),$name);
  $resource->setAssignedUser($assignedUser);
  if($color!==null)$resource->setColor($color);
  $this->em->persist($resource);
  $this->em->flush();
  return new JsonResponse(['resource'=>$this->resourceData($resource)],201);
 }
 #[Route('/api/v1/admin/appointments/availability',methods:['GET','POST'])] public function availability(Request $r):JsonResponse{if(!$this->admin())return new JsonResponse(['message'=>'Forbidden'],403);if($r->isMethod('GET')){$items=$this->em->createQueryBuilder()->select('availability')->from(AppointmentAvailability::class,'availability')->join('availability.resource','resource')->where('resource.tenant=:tenant')->setParameter('tenant',$this->tenant->get())->getQuery()->getResult();$groups=[];foreach($items as $item){$key=$item->getResource()->getId().'-'.$item->getType()->getId().'-'.$item->getStartsAt().'-'.$item->getEndsAt();if(!isset($groups[$key]))$groups[$key]=['id'=>$item->getId(),'resourceId'=>$item->getResource()->getId(),'typeId'=>$item->getType()->getId(),'weekdays'=>[],'startsAt'=>$item->getStartsAt(),'endsAt'=>$item->getEndsAt()];$groups[$key]['weekdays'][]=$item->getWeekday();}foreach($groups as &$group)sort($group['weekdays']);return new JsonResponse(['availability'=>array_values($groups)]);} $d=$r->toArray();$resource=$this->em->getRepository(AppointmentResource::class)->findOneBy(['id'=>(int)($d['resourceId']??0),'tenant'=>$this->tenant->get()]);$type=$this->typeFor((int)($d['typeId']??0));$days=$d['weekdays']??[];$startsAt=(string)($d['startsAt']??'');$endsAt=(string)($d['endsAt']??'');if(!$resource instanceof AppointmentResource||!$type||!is_array($days)||!preg_match('/^\d\d:\d\d$/',$startsAt)||!preg_match('/^\d\d:\d\d$/',$endsAt)||$startsAt>=$endsAt)return new JsonResponse(['message'=>'Ungültige Verfügbarkeit.'],422);foreach($days as $day){$weekday=(int)$day;if($weekday<1||$weekday>7||!$this->availabilityFree($resource,$weekday,$startsAt,$endsAt))return new JsonResponse(['message'=>'Diese Person ist in diesem Zeitraum bereits verfügbar.'],422);}foreach(array_unique(array_map('intval',$days)) as $day)$this->em->persist(new AppointmentAvailability($resource,$type,$day,$startsAt,$endsAt));$this->em->flush();return new JsonResponse(status:201);}
 #[Route('/api/v1/admin/appointments/types/{id}',methods:['PATCH'])] public function updateType(int $id,Request $r):JsonResponse{if(!$this->admin())return new JsonResponse(['message'=>'Forbidden'],403);$type=$this->typeFor($id);$d=$r->toArray();$duration=(int)($d['durationMinutes']??0);if(!$type||trim((string)($d['title']??''))===''||$duration<5)return new JsonResponse(['message'=>'Ungültige Terminart.'],422);$type->update(trim((string)$d['title']),trim((string)($d['description']??''))?:null,$duration,max(0,(int)($d['bufferMinutes']??5)),(bool)($d['isVisible']??true));$this->em->flush();return new JsonResponse(['type'=>$this->type($type)]);}
 #[Route('/api/v1/admin/appointments/resources/{id}',methods:['PATCH'])]
 public function updateResource(int $id,Request $r):JsonResponse
 {
  if(!$this->admin())return new JsonResponse(['message'=>'Forbidden'],403);
  $resource=$this->em->getRepository(AppointmentResource::class)->findOneBy(['id'=>$id,'tenant'=>$this->tenant->get()]);
  $data=$r->toArray();
  $name=trim((string)($data['name']??''));
  $color=$data['color']??null;
  $assignedUser=array_key_exists('userId',$data)?$this->staffUser($data['userId']):$resource?->getAssignedUser();
  if(!$resource instanceof AppointmentResource||$name===''||mb_strlen($name)>160||($color!==null&&(!is_string($color)||!preg_match('/^#[0-9a-fA-F]{6}$/',$color)))||(array_key_exists('userId',$data)&&$data['userId']!==null&&$assignedUser===null))return new JsonResponse(['message'=>'Name, Farbe oder Mitarbeiterkonto ungültig.'],422);
  $resource->update($name,'person',(bool)($data['isActive']??true));
  $resource->setAssignedUser($assignedUser);
  if($color!==null)$resource->setColor($color);
  $this->em->flush();
  return new JsonResponse(['resource'=>$this->resourceData($resource)]);
 }
 private function staffUser(mixed $id): ?User
 {
    if ($id === null) return null;
    if (!is_int($id) || $id < 1) return null;
    $user = $this->em->getRepository(User::class)->find($id);
    if (!$user instanceof User) return null;
    $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
    return $membership !== null && array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles()) ? $user : null;
 }
 private function resourceData(AppointmentResource $resource): array
 {
    return ['id' => $resource->getId(), 'name' => $resource->getName(), 'color' => $resource->getColor(), 'userId' => $resource->getAssignedUser()?->getId(), 'isActive' => $resource->isActive()];
 }
 #[Route('/api/v1/admin/appointments/availability/{id}',methods:['PATCH'])] public function updateAvailability(int $id,Request $r):JsonResponse{if(!$this->admin())return new JsonResponse(['message'=>'Forbidden'],403);$availability=$this->em->createQueryBuilder()->select('availability')->from(AppointmentAvailability::class,'availability')->join('availability.resource','resource')->where('availability.id=:id AND resource.tenant=:tenant')->setParameter('id',$id)->setParameter('tenant',$this->tenant->get())->getQuery()->getOneOrNullResult();$d=$r->toArray();$resource=$this->em->getRepository(AppointmentResource::class)->findOneBy(['id'=>(int)($d['resourceId']??0),'tenant'=>$this->tenant->get()]);$type=$this->typeFor((int)($d['typeId']??0));$days=$d['weekdays']??[];$startsAt=(string)($d['startsAt']??'');$endsAt=(string)($d['endsAt']??'');if(!$availability instanceof AppointmentAvailability||!$resource instanceof AppointmentResource||!$type||!is_array($days)||[]===$days||!preg_match('/^\d\d:\d\d$/',$startsAt)||!preg_match('/^\d\d:\d\d$/',$endsAt)||$startsAt>=$endsAt)return new JsonResponse(['message'=>'Ungültige Verfügbarkeit.'],422);$siblings=$this->em->getRepository(AppointmentAvailability::class)->findBy(['resource'=>$availability->getResource(),'type'=>$availability->getType(),'startsAt'=>$availability->getStartsAt(),'endsAt'=>$availability->getEndsAt()]);$exclude=array_map(fn(AppointmentAvailability $item)=>$item->getId(),$siblings);foreach($days as $day){$weekday=(int)$day;if($weekday<1||$weekday>7||!$this->availabilityFree($resource,$weekday,$startsAt,$endsAt,$exclude))return new JsonResponse(['message'=>'Diese Person ist in diesem Zeitraum bereits verfügbar.'],422);}foreach($siblings as $item)$this->em->remove($item);foreach(array_unique(array_map('intval',$days)) as $day)$this->em->persist(new AppointmentAvailability($resource,$type,$day,$startsAt,$endsAt));$this->em->flush();return new JsonResponse(status:204);}
 private function slotsFor(AppointmentType $type,\DateTimeImmutable $day):array{$slots=[];$weekday=(int)$day->format('N');$occupiedMinutes=$type->getDurationMinutes()+$type->getBufferMinutes();$now=new \DateTimeImmutable();foreach($this->em->getRepository(AppointmentAvailability::class)->findBy(['type'=>$type,'weekday'=>$weekday]) as $rule){$resource=$rule->getResource();if(!$resource->isActive())continue;$cursor=new \DateTimeImmutable($day->format('Y-m-d').' '.$rule->getStartsAt());$limit=new \DateTimeImmutable($day->format('Y-m-d').' '.$rule->getEndsAt());while($cursor->modify('+'.$occupiedMinutes.' minutes')<=$limit){$end=$cursor->modify('+'.$type->getDurationMinutes().' minutes');if($cursor>$now&&!$this->blocker->blocks($this->tenant->get(),$type,$cursor,$end)&&$this->isFree($resource,$cursor,$type))$slots[]=['resourceId'=>$resource->getId(),'startsAt'=>$cursor->format(DATE_ATOM),'endsAt'=>$end->format(DATE_ATOM)];$cursor=$cursor->modify('+'.$occupiedMinutes.' minutes');}}usort($slots,fn($a,$b)=>$a['startsAt']<=>$b['startsAt']);return $slots;}
 private function isFree(AppointmentResource $resource,\DateTimeImmutable $start,AppointmentType $type):bool{$end=$start->modify('+'.($type->getDurationMinutes()+$type->getBufferMinutes()).' minutes');$appointments=$this->em->getRepository(Appointment::class)->findBy(['resource'=>$resource]);foreach($appointments as $appointment){if(!$appointment->occupiesSlot())continue;$occupiedUntil=$appointment->getEndsAt()->modify('+'.$appointment->getType()->getBufferMinutes().' minutes');if($appointment->getStartsAt()<$end&&$occupiedUntil>$start)return false;}return true;} private function availabilityFree(AppointmentResource $resource,int $weekday,string $startsAt,string $endsAt,array $excludeIds=[]):bool{foreach($this->em->getRepository(AppointmentAvailability::class)->findBy(['resource'=>$resource,'weekday'=>$weekday]) as $rule){if(!in_array($rule->getId(),$excludeIds,true)&&$rule->getStartsAt()<$endsAt&&$rule->getEndsAt()>$startsAt)return false;}return true;}
 private function typeFor(int $id):?AppointmentType{$x=$this->em->getRepository(AppointmentType::class)->find($id);return $x instanceof AppointmentType&&$x->getTenant()===$this->tenant->get()?$x:null;} private function type(AppointmentType $t):array{return ['id'=>$t->getId(),'title'=>$t->getTitle(),'description'=>$t->getDescription(),'durationMinutes'=>$t->getDurationMinutes(),'bufferMinutes'=>$t->getBufferMinutes(),'isVisible'=>$t->isVisible()];} private function appointment(Appointment $a):array{return ['id'=>$a->getId(),'type'=>$a->getType()->getTitle(),'resource'=>$a->getTenant()->showsAppointmentStaffNames()?$a->getResource()->getName():null,'startsAt'=>$a->getStartsAt()->format(DATE_ATOM),'endsAt'=>$a->getEndsAt()->format(DATE_ATOM),'status'=>$a->getStatus()];} private function customer():?User{$u=$this->security->getUser();return $u instanceof User&&$this->memberships->hasCustomerMembershipFor($u,$this->tenant->get())?$u:null;}private function admin():bool{$u=$this->security->getUser();if(!$u instanceof User)return false;$m=$this->memberships->findForUserAndTenant($u,$this->tenant->get());return $m&&[]!==array_intersect(['ROLE_TENANT_STAFF','ROLE_TENANT_ADMIN'],$m->getRoles());}}
