import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AchievementCelebrationComponent } from './shared/feedback/achievement-celebration.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, AchievementCelebrationComponent],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App {}
