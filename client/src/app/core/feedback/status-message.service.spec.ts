import { TestBed } from '@angular/core/testing';
import { StatusMessageService } from './status-message.service';

describe('StatusMessageService', () => {
  let service: StatusMessageService;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [StatusMessageService] });
    service = TestBed.inject(StatusMessageService);
  });

  it('shows an error message', () => {
    service.error('Die Datei konnte nicht gelesen werden.');

    expect(service.current()).toMatchObject({
      kind: 'error',
      text: 'Die Datei konnte nicht gelesen werden.',
    });
  });

  it('dismisses the current message', () => {
    service.show('Gespeichert');

    service.dismiss();

    expect(service.isVisible()).toBe(false);
  });

  it('removes a dismissed message after its transition', async () => {
    service.show('Gespeichert');
    service.dismiss();

    await new Promise((resolve) => setTimeout(resolve, 250));

    expect(service.current()).toBeNull();
  });
});
