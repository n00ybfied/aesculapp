// Push only: deliberately no fetch handler or caches for private API data.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('push', event => {
 let payload = {};
 try { payload = event.data ? event.data.json() : {}; } catch { payload = {}; }
 const title = typeof payload.title === 'string' ? payload.title : 'AesculApp';
 const body = typeof payload.body === 'string' ? payload.body : 'Sie haben eine neue Benachrichtigung.';
 const url = typeof payload.url === 'string' && payload.url.startsWith('/') ? payload.url : '/';
 const tag = typeof payload.tag === 'string' ? payload.tag : 'aesculapp-notification';
 event.waitUntil(self.registration.showNotification(title, { body, tag, data: { url } }));
});
self.addEventListener('notificationclick', event => {
 event.notification.close();
 event.waitUntil((async () => {
  const windows = await self.clients.matchAll({type:'window',includeUncontrolled:true});
  const path = typeof event.notification.data?.url === 'string' ? event.notification.data.url : '/';
  const target = new URL(path,self.location.origin).href;
  for(const client of windows){
   if(new URL(client.url).origin===self.location.origin){await client.navigate(target);return client.focus();}
  }
  return self.clients.openWindow(target);
 })());
});
