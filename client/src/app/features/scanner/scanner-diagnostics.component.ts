import { Component, input } from '@angular/core';
import type { QrScannerDiagnostics } from '../../core/scanner/qr-scanner.service';

@Component({
  selector: 'app-scanner-diagnostics',
  templateUrl: './scanner-diagnostics.component.html',
})
export class ScannerDiagnosticsComponent {
  readonly diagnostics = input<QrScannerDiagnostics | null>(null);
  readonly rawQrValue = input<string | null>(null);
}
