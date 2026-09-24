import { ConfirmDialogService } from './confirm-dialog.service';

describe('ConfirmDialogService', () => {
  it('keeps an action pending until the custom dialog is answered', async () => {
    const dialogs = new ConfirmDialogService();
    const result = dialogs.confirm('Wirklich löschen?', { destructive: true });
    expect(dialogs.current()?.message).toBe('Wirklich löschen?');
    expect(dialogs.current()?.destructive).toBe(true);
    dialogs.answer(false);
    await expect(result).resolves.toBe(false);
    expect(dialogs.current()).toBeNull();
  });
});
