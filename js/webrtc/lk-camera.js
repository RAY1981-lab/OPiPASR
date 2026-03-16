(function () {
  'use strict';

  var STREAM_ID = 1;
  var elements = {
    status: document.getElementById('lkCamStatus'),
    hint: document.getElementById('lkCamHint'),
    video: document.getElementById('lkCamVideo'),
    connect: document.getElementById('lkCamConnect'),
    disconnect: document.getElementById('lkCamDisconnect'),
    reconnect: document.getElementById('lkCamReconnect')
  };

  if (!elements.status || !elements.video) {
    return;
  }

  var janus = null;
  var streaming = null;
  var remoteStream = null;

  function isPrivateHost(hostname) {
    return hostname === 'localhost' || hostname === '127.0.0.1' || /^10\./.test(hostname) || /^192\.168\./.test(hostname) || /^172\.(1[6-9]|2\d|3[01])\./.test(hostname);
  }

  function getJanusServerUrl() {
    var override = '';
    try {
      override = (window.localStorage && localStorage.getItem('janusServer')) || '';
    } catch (e) {
      override = '';
    }
    if (override) {
      return override;
    }

    if (isPrivateHost(window.location.hostname)) {
      return 'ws://192.168.8.118:8188';
    }

    return 'wss://opipasr.online/janusws';
  }

  function setStatus(state, details) {
    elements.status.textContent = details ? state + ' — ' + details : state;
  }

  function showHint(text) {
    if (!elements.hint) {
      return;
    }
    elements.hint.textContent = text || '';
  }

  function setError(details) {
    setStatus('error', details || 'Неизвестная ошибка');
  }

  function clearVideo() {
    if (remoteStream) {
      remoteStream.getTracks().forEach(function (track) {
        track.stop();
      });
    }
    remoteStream = null;
    elements.video.srcObject = null;
  }

  function destroySession() {
    clearVideo();

    if (streaming) {
      try {
        streaming.hangup();
      } catch (e) {
        // noop
      }
      try {
        streaming.detach();
      } catch (e) {
        // noop
      }
    }

    streaming = null;

    if (janus) {
      try {
        janus.destroy();
      } catch (e) {
        // noop
      }
    }

    janus = null;
  }

  function startWatching() {
    if (!streaming) {
      setError('Плагин streaming не инициализирован');
      return;
    }

    setStatus('watching');
    streaming.send({
      message: {
        request: 'watch',
        id: STREAM_ID
      }
    });
  }

  function connect() {
    var janusServer = getJanusServerUrl();

    showHint('');

    if (window.location.protocol === 'https:' && janusServer.indexOf('ws://') === 0) {
      setError('Mixed content: откройте кабинет в LAN по HTTP или настройте WSS прокси (/janusws).');
      showHint('Если кабинет открыт по HTTPS, нужен WSS прокси. Сейчас используйте LAN или настройте /janusws.');
      return;
    }

    if (!window.adapter) {
      setError('adapter.js не загружен');
      return;
    }

    if (!window.Janus) {
      setError('janus.js не загружен');
      return;
    }

    destroySession();
    setStatus('init');

    Janus.init({
      debug: false,
      callback: function () {
        setStatus('connecting');
        janus = new Janus({
          server: janusServer,
          success: function () {
            janus.attach({
              plugin: 'janus.plugin.streaming',
              success: function (pluginHandle) {
                streaming = pluginHandle;
                startWatching();
              },
              error: function (error) {
                setError('attach: ' + error);
              },
              iceState: function (state) {
                setStatus('connecting', 'ICE: ' + state);
              },
              webrtcState: function (on) {
                setStatus(on ? 'watching' : 'connecting', 'WebRTC: ' + (on ? 'up' : 'down'));
              },
              onmessage: function (msg, jsep) {
                var result = msg && msg.result;
                if (result && result.status) {
                  setStatus('watching', result.status);
                }

                if (msg && msg.error) {
                  setError(msg.error);
                  return;
                }

                if (jsep) {
                  streaming.createAnswer({
                    jsep: jsep,
                    tracks: [{ type: 'data' }],
                    success: function (jsepAnswer) {
                      streaming.send({
                        message: { request: 'start' },
                        jsep: jsepAnswer
                      });
                    },
                    error: function (error) {
                      setError('createAnswer: ' + error);
                    }
                  });
                }
              },
              onremotetrack: function (track, mid, on) {
                if (!on || !track || track.kind !== 'video') {
                  return;
                }
                remoteStream = new MediaStream([track]);
                elements.video.srcObject = remoteStream;
                setStatus('playing');
              },
              oncleanup: function () {
                clearVideo();
                setStatus('init');
              }
            });
          },
          error: function (error) {
            setError('Janus: ' + error);
          },
          destroyed: function () {
            clearVideo();
            setStatus('init');
          }
        });
      }
    });
  }

  function disconnect() {
    destroySession();
    setStatus('init');
  }

  elements.connect && elements.connect.addEventListener('click', connect);
  elements.disconnect && elements.disconnect.addEventListener('click', disconnect);
  elements.reconnect && elements.reconnect.addEventListener('click', function () {
    disconnect();
    connect();
  });

  setStatus('init');
})();
