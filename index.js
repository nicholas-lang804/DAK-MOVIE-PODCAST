// Entry point for the Node.js toolchain on the hosting platform.
// This is a PHP application — no server-side JavaScript is executed.
// livekit-client is consumed in the browser via the prebuilt bundle at
// assets/js/livekit-client.umd.js; this module simply re-exports the package
// so `npm install` / build steps have a valid entry file.
module.exports = require('livekit-client');
