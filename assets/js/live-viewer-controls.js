(function () {
  const box = document.getElementById('livekitPlayer');
  const stage = document.getElementById('livePlayerStage');
  const controls = document.getElementById('liveViewControls');
  const fullscreenButton = document.getElementById('screenFullscreen');
  const pictureInPictureButton = document.getElementById('screenPictureInPicture');
  if (!box || !stage || !controls || !fullscreenButton || !pictureInPictureButton) return;

  let sharedScreen = null;

  function getPictureInPictureSupport(video) {
    return Boolean(
      document.pictureInPictureEnabled &&
      video &&
      typeof video.requestPictureInPicture === 'function'
    ) || Boolean(video && typeof video.webkitSetPresentationMode === 'function');
  }

  function updatePictureInPictureLabel() {
    const active = document.pictureInPictureElement === sharedScreen ||
      sharedScreen?.webkitPresentationMode === 'picture-in-picture';
    pictureInPictureButton.textContent = active ? 'Back to page' : 'Picture in picture';
  }

  box.addEventListener('tmz-screen-share', function (event) {
    if (event.detail.active) {
      sharedScreen = event.detail.element;
      controls.hidden = false;
      box.classList.add('is-screen-sharing');
      pictureInPictureButton.hidden = !getPictureInPictureSupport(sharedScreen);
      updatePictureInPictureLabel();
      sharedScreen.addEventListener('enterpictureinpicture', updatePictureInPictureLabel);
      sharedScreen.addEventListener('leavepictureinpicture', updatePictureInPictureLabel);
      sharedScreen.addEventListener('webkitpresentationmodechanged', updatePictureInPictureLabel);
      return;
    }

    if (document.pictureInPictureElement === sharedScreen) {
      document.exitPictureInPicture().catch(function (error) {
        console.error('Could not close the screen-share picture-in-picture window:', error);
      });
    } else if (sharedScreen?.webkitPresentationMode === 'picture-in-picture') {
      sharedScreen.webkitSetPresentationMode('inline');
    }
    if (document.fullscreenElement === sharedScreen || document.fullscreenElement === stage) {
      document.exitFullscreen().catch(function (error) {
        console.error('Could not close the full-screen screen share:', error);
      });
    }
    sharedScreen = null;
    controls.hidden = true;
    box.classList.remove('is-screen-sharing');
  });

  fullscreenButton.addEventListener('click', async function () {
    if (!sharedScreen) return;
    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen();
      } else {
        // Fullscreen the whole stage (not just the video) so the mic/camera controls stay reachable.
        try {
          await stage.requestFullscreen();
        } catch (stageError) {
          await (sharedScreen.requestFullscreen ? sharedScreen.requestFullscreen() : stage.requestFullscreen());
        }
      }
    } catch (error) {
      console.error('Full-screen screen sharing failed:', error);
      controls.dataset.message = 'Full screen is not available in this browser.';
    }
  });

  pictureInPictureButton.addEventListener('click', async function () {
    if (!sharedScreen) return;
    try {
      if (document.pictureInPictureElement === sharedScreen) {
        await document.exitPictureInPicture();
      } else if (sharedScreen.webkitPresentationMode === 'picture-in-picture') {
        sharedScreen.webkitSetPresentationMode('inline');
      } else if (typeof sharedScreen.requestPictureInPicture === 'function') {
        await sharedScreen.requestPictureInPicture();
      } else if (typeof sharedScreen.webkitSetPresentationMode === 'function') {
        sharedScreen.webkitSetPresentationMode('picture-in-picture');
      }
      updatePictureInPictureLabel();
    } catch (error) {
      console.error('Picture-in-picture screen sharing failed:', error);
      controls.dataset.message = 'Picture in picture could not start. Try full screen instead.';
    }
  });

  document.addEventListener('fullscreenchange', function () {
    fullscreenButton.textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
  });
})();
