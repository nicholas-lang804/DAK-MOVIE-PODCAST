(function(){
  const feed=document.getElementById('adminLiveChat');
  if(!feed)return;
  function decode(payload){try{return JSON.parse(new TextDecoder().decode(payload));}catch(e){return null;}}
  window.addEventListener('tmz-live-data',function(event){
    const data=decode(event.detail.payload); if(!data||data.type!=='chat')return;
    const participant=event.detail.participant;
    const row=document.createElement('div'); row.className='live-chat-identity-message';
    const avatar=document.createElement('span'); avatar.className='live-avatar-fallback';
    const displayName=participant?.name||participant?.identity||'Viewer';
    avatar.textContent=displayName.charAt(0).toUpperCase();
    const content=document.createElement('p'),name=document.createElement('strong'),message=document.createElement('span');
    name.textContent=displayName; message.textContent=data.message||''; content.append(name,message); row.append(avatar,content); feed.appendChild(row); feed.scrollTop=feed.scrollHeight;
  });
})();
