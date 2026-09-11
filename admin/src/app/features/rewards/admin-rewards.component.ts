import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AdminRewardService, AdminReward } from '../../core/rewards/admin-reward.service';
@Component({selector:'app-admin-rewards',imports:[RouterLink],templateUrl:'./admin-rewards.component.html',styleUrl:'./admin-rewards.component.css'})
export class AdminRewardsComponent {
 private readonly service=inject(AdminRewardService);
 protected readonly rewards=signal<readonly AdminReward[]>([]);
 protected readonly loading=signal(true);
 protected readonly busy=signal(false);
 protected readonly error=signal('');
 constructor(){void this.load();}
 private async load(){try{this.rewards.set(await this.service.list());}catch{this.error.set('Gutscheine konnten nicht geladen werden.');}finally{this.loading.set(false);}}
 protected async toggle(reward:AdminReward){if(this.busy())return;this.busy.set(true);try{await this.service.toggle(reward);await this.load();}catch{this.error.set('Sichtbarkeit konnte nicht geändert werden.');}finally{this.busy.set(false);}}
 protected async remove(reward:AdminReward){if(this.busy()||!confirm('„'+reward.title+'“ wirklich löschen?'))return;this.busy.set(true);try{await this.service.remove(reward.id);await this.load();}catch{this.error.set('Gutschein konnte nicht gelöscht werden.');}finally{this.busy.set(false);}}
}
