import { nativeCustomerPath } from './native-link.service';

describe('nativeCustomerPath', () => {
  const origin = 'https://app.stadtapotheke-trofaiach.at';
  const token = 'a'.repeat(64);

  it('keeps the same auth route and token as the PWA link', () => {
    expect(nativeCustomerPath(`${origin}/passwort-zuruecksetzen?token=${token}`, origin))
      .toBe(`/passwort-zuruecksetzen?token=${token}`);
    expect(nativeCustomerPath(`${origin}/e-mail-bestaetigen?token=${token}`, origin))
      .toBe(`/e-mail-bestaetigen?token=${token}`);
    expect(nativeCustomerPath(`${origin}/e-mail-aendern?token=${token}`, origin))
      .toBe(`/e-mail-aendern?token=${token}`);
  });

  it('maps customer invitation and notification links to their PWA routes', () => {
    const referral = 'b'.repeat(32);
    expect(nativeCustomerPath(`${origin}/registrierung?ref=${referral}`, origin)).toBe(`/registrierung?ref=${referral}`);
    expect(nativeCustomerPath(`${origin}/familie?token=${token}`, origin)).toBe(`/familie?token=${token}`);
    for (const path of ['/familie', '/termine/meine', '/chat', '/freunde-einladen', '/profil/bearbeiten', '/news/42']) {
      expect(nativeCustomerPath(`${origin}${path}`, origin)).toBe(path);
    }
  });

  it('rejects foreign, admin, malformed and unrelated links', () => {
    expect(nativeCustomerPath(`https://aesculapp.floatbox.at/passwort-zuruecksetzen?token=${token}`, origin)).toBeNull();
    expect(nativeCustomerPath(`https://admin.stadtapotheke-trofaiach.at/passwort-zuruecksetzen?token=${token}`, origin)).toBeNull();
    expect(nativeCustomerPath(`${origin}/dashboard?token=${token}`, origin)).toBeNull();
    expect(nativeCustomerPath(`${origin}/passwort-zuruecksetzen?token=short`, origin)).toBeNull();
    expect(nativeCustomerPath(`${origin}/passwort-zuruecksetzen?token=${token}&next=/chat`, origin)).toBeNull();
    expect(nativeCustomerPath(`${origin}/passwort-zuruecksetzen?token=${token}#fragment`, origin)).toBeNull();
    expect(nativeCustomerPath(`${origin}/registrierung?ref=invalid`, origin)).toBeNull();
    expect(nativeCustomerPath(`${origin}/news/abc`, origin)).toBeNull();
  });

  it('allows the local Android test link without accepting another origin', () => {
    expect(nativeCustomerPath(`http://localhost:4200/passwort-zuruecksetzen?token=${token}`, 'http://localhost:4200'))
      .toBe(`/passwort-zuruecksetzen?token=${token}`);
  });
});
