// Push only: deliberately no fetch handler or caches for private API data.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('push', event => {
 event.waitUntil(self.registration.showNotification('Neue Antwort', {
  body: 'Sie haben eine neue Antwort von Ihrer Apotheke.',
  tag: 'apotheke-chat-reply',
  data: { url: '/chat' }
 }));
});
self.addEventListener('notificationclick', event => {
 event.notification.close();
 event.waitUntil((async () => {
  const windows = await self.clients.matchAll({type:'window',includeUncontrolled:true});
  const target = new URL('/chat',self.location.origin).href;
  for(const client of windows){
   if(new URL(client.url).origin===self.location.origin){await client.navigate(target);return client.focus();}
  }
  return self.clients.openWindow(target);
 })());
});
