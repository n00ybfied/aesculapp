import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';
export interface AdminReward { id:number; title:string; subtitle:string; description:string; imageUrl:string|null; requiredPoints:number; isVisible:boolean; }
@Injectable({providedIn:'root'})
export class AdminRewardService {
  private readonly http=inject(HttpClient);
  private readonly auth=inject(AdminAuthService);
  private readonly api=['localhost','127.0.0.1'].includes(location.hostname)?'http://localhost:6080/api/v1':'https://api.aesculapp.floatbox.at/api/v1';
  private options(){return {headers:new HttpHeaders({Authorization:'Bearer '+this.auth.accessToken()})};}
  async list(){return (await firstValueFrom(this.http.get<{rewards:AdminReward[]}>(this.api+'/admin/rewards',this.options()))).rewards;}
  async get(id:number){return (await firstValueFrom(this.http.get<{reward:AdminReward}>(this.api+'/admin/rewards/'+id,this.options()))).reward;}
  async save(id:number|null,data:FormData){return firstValueFrom(this.http.post(this.api+'/admin/rewards'+(id===null?'':'/'+id+'/update'),data,this.options()));}
  async toggle(item:AdminReward){return firstValueFrom(this.http.patch(this.api+'/admin/rewards/'+item.id+'/visibility',{isVisible:!item.isVisible},this.options()));}
  async remove(id:number){return firstValueFrom(this.http.delete(this.api+'/admin/rewards/'+id,this.options()));}
}
