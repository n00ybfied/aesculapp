import { ConfirmDialogService } from './confirm-dialog.service';

describe('ConfirmDialogService', () => {
  it('resolves only after the custom dialog is confirmed', async () => {
    const dialogs = new ConfirmDialogService();
    const result = dialogs.confirm('Fortfahren?');
    expect(dialogs.current()?.message).toBe('Fortfahren?');
    dialogs.answer(true);
    await expect(result).resolves.toBe(true);
    expect(dialogs.current()).toBeNull();
  });
});
