import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';
import { rewardStorageKey } from '../rewards/reward.repository';
import { ThemeService } from '../theme/theme.service';
import { MockReceiptRepository } from './receipt.repository';

describe('MockReceiptRepository', () => {
  let repository: MockReceiptRepository;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    localStorage.removeItem(rewardStorageKey);
    TestBed.configureTestingModule({
      providers: [
        MockReceiptRepository,
        { provide: ThemeService, useValue: { pointsPerEuro: 10 } },
        { provide: API_BASE_URL, useValue: 'http://localhost:6080/api/v1' },
        { provide: AuthService, useValue: { accessToken: () => 'test-access-token' } },
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    });
    repository = TestBed.inject(MockReceiptRepository);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  const cashRegisterQr = '_HA_0_K26/000498_2026-08-05T08:57:36_0,00_0,00_0,00_0,00_26,50_0_0_0_MEUCIGfxx83XX+b8GEjJbjlubGG+v8dk13yz/AgH2DOEGadAAiEAiRFNsdfBxPx4DSaVqOpFjt4IglSldfEh3zIwIaiZVAY=';

  it('submits the raw QR value to the API when importing a receipt', async () => {
    const preview = await repository.createPreview(cashRegisterQr);
    const resultPromise = repository.import(preview.id);
    const request = httpTesting.expectOne('http://localhost:6080/api/v1/receipts/import');
    expect(request.request.body).toEqual({ rawQrValue: cashRegisterQr });
    request.flush({ addedPoints: 265 });
    await expect(resultPromise).resolves.toEqual({ addedPoints: 265 });
  });

  it('creates a receipt preview from a cash-register QR value', async () => {
    const preview = await repository.createPreview(cashRegisterQr);

    expect(preview).toMatchObject({
      id: 'receipt-K26/000498-2026-08-05T08:57:36',
      eligibleCents: 2650,
      earnedPoints: 265,
    });
  });

  it('returns the credited points after importing a receipt', async () => {
    const preview = await repository.createPreview(cashRegisterQr);
    const resultPromise = repository.import(preview.id);
    httpTesting.expectOne('http://localhost:6080/api/v1/receipts/import').flush({ addedPoints: 265 });
    const result = await resultPromise;

    expect(result.addedPoints).toBe(265);
  });
});
