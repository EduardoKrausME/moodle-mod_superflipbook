# Browser dependencies

The application requires **PDF.js 2.16.105** and **StPageFlip 2.0.7** to be distributed **inside the plugin**. The Moodle website serves them under `/mod/superflipbook/thirdparty/` with no CDN connections at runtime.

Run `bash thirdparty/fetch-assets.sh` on a development machine with internet access, validate the resulting SHA256SUMS against the release artifacts, then package the files alongside the plugin. Do not deploy this unfinished source archive until the three referenced JS files exist. The files could not be downloaded from the sandbox network in which this prototype was generated.

PDF.js is distributed by Mozilla under Apache-2.0. StPageFlip is distributed by Nodlik under MIT. Retain their notices when distributing compiled bundles.
