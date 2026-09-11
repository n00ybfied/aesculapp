import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { OpenChatBadgeComponent } from '../../shared/open-chat-badge.component';

@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink, OpenChatBadgeComponent],
  templateUrl: './admin-dashboard.component.html',
  styleUrl: './admin-dashboard.component.css',
})
export class AdminDashboardComponent {}
