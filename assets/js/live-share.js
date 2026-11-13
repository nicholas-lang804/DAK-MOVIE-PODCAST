(function () {
  const panel = document.getElementById('liveShareTools');
  if (!panel) return;

  const url = panel.dataset.shareUrl || '';
  const title = panel.dataset.shareTitle || 'THE DAK FILM PODCAST MEDIA Live';
  const description = panel.querySelector('.live-share-preview p')?.textContent.trim() || '';
  const message = [title, description, url].filter(Boolean).join('\n');
  const status = document.getElementById('liveShareStatus');
  const linkInput = document.getElementById('liveShareLink');
  const shareButton = document.getElementById('shareLiveShow');
  const copyButton = document.getElementById('copyLiveShowLink');
  const whatsappLink = document.getElementById('whatsappLiveShow');
  const emailLink = document.getElementById('emailLiveShow');

  if (whatsappLink) whatsappLink.href = 'https://wa.me/?text=' + encodeURIComponent(message);
  if (emailLink) {
    emailLink.href = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(message);
  }

  async function copyLink() {
    try {
      if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
      } else {
        linkInput.focus();
        linkInput.select();
        if (!document.execCommand('copy')) throw new Error('Clipboard access is unavailable in this browser.');
      }
      status.textContent = 'Live-show link copied.';
    } catch (error) {
      console.error('Could not copy the live-show link:', error);
      status.textContent = 'Copy failed. Select and copy the link above.';
      linkInput.focus();
      linkInput.select();
    }
  }

  copyButton?.addEventListener('click', copyLink);
  shareButton?.addEventListener('click', async function () {
    if (!navigator.share) {
      await copyLink();
      return;
    }
    try {
      await navigator.share({ title, text: description, url });
      status.textContent = 'Share sheet opened.';
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('Could not share the live show:', error);
        status.textContent = 'Sharing was unavailable. Copy the link or use WhatsApp or Email.';
      }
    }
  });
})();
