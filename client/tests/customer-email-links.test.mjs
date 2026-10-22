import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const association = JSON.parse(readFileSync(new URL('../public/.well-known/apple-app-site-association', import.meta.url), 'utf8'));
const androidManifest = readFileSync(new URL('../android-apotheke/app/src/main/AndroidManifest.xml', import.meta.url), 'utf8');
const iosEntitlements = readFileSync(new URL('../ios/App/App/App.entitlements', import.meta.url), 'utf8');
const paths = association.applinks.details[0].paths;

const customerEmailRoutes = [
  ['/e-mail-bestaetigen', 'Registrierung und Aktivierung'],
  ['/e-mail-aendern', 'E-Mail- und Benutzernamenswechsel'],
  ['/passwort-zuruecksetzen', 'Passwort zurücksetzen'],
  ['/registrierung', 'Freundeseinladung'],
  ['/familie', 'Familieneinladung und Punkteteilung'],
  ['/termine/meine', 'Terminbuchung, Bestätigung und Erinnerung'],
  ['/freunde-einladen', 'Erfolgreiche Freundeseinladung'],
];

test('iOS-App-ID und Associated Domain passen zur Apotheken-PWA', () => {
  assert.equal(association.applinks.details[0].appID, 'R328924CNP.at.floatbox.aesculapp.sta.test');
  assert.ok(iosEntitlements.includes('applinks:app.stadtapotheke-trofaiach.at'));
});

for (const [route, occasion] of customerEmailRoutes) {
  test(`${occasion}: ${route} ist als iOS- und Android-App-Link registriert`, () => {
    assert.ok(paths.includes(route), `iOS AASA enthält ${route} nicht`);
    assert.ok(androidManifest.includes(`android:path="${route}"`), `Android-Manifest enthält ${route} nicht`);
  });
}

test('Admin-Links werden nicht als Kunden-App-Links registriert', () => {
  assert.ok(!paths.includes('/einladung-annehmen'));
  assert.ok(!androidManifest.includes('android:path="/einladung-annehmen"'));
});
