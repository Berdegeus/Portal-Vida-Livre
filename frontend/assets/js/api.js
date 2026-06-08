const HybridCrypto = (() => {
  const PUBKEY_STORAGE_KEY = 'portal-vida-livre-pubkey';
  let _publicKeyCache = null;

  const pemToCryptoKey = async (pem) => {
    const b64 = pem.replace(/-----[^-]+-----/g, '').replace(/\s/g, '');
    const der = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    return crypto.subtle.importKey(
      'spki', der.buffer,
      { name: 'RSA-OAEP', hash: 'SHA-256' },
      false, ['wrapKey']
    );
  };

  const fetchPublicKey = async () => {
    if (_publicKeyCache) return _publicKeyCache;
    const cachedPem = sessionStorage.getItem(PUBKEY_STORAGE_KEY);
    if (cachedPem) {
      _publicKeyCache = await pemToCryptoKey(cachedPem);
      return _publicKeyCache;
    }
    const res = await fetch('/backend/api/public-key.php');
    const json = await res.json();
    const pem = json.data.public_key;
    sessionStorage.setItem(PUBKEY_STORAGE_KEY, pem);
    _publicKeyCache = await pemToCryptoKey(pem);
    return _publicKeyCache;
  };

  const encryptPayload = async (data) => {
    if (!window.crypto?.subtle) {
      console.error('[HybridCrypto] WebCrypto nao disponivel; enviando sem criptografia.');
      return data;
    }
    try {
      const publicKey = await fetchPublicKey();
      const sessionKey = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 }, true, ['encrypt']
      );
      const iv = crypto.getRandomValues(new Uint8Array(12));
      const encoded = new TextEncoder().encode(JSON.stringify(data));
      const encrypted = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, sessionKey, encoded);
      const wrappedKey = await crypto.subtle.wrapKey('raw', sessionKey, publicKey, { name: 'RSA-OAEP' });
      const toB64 = buf => btoa(String.fromCharCode(...new Uint8Array(buf)));
      return {
        session_key_encrypted: toB64(wrappedKey),
        data_encrypted: toB64(encrypted),
        iv: toB64(iv),
      };
    } catch (e) {
      console.error('[HybridCrypto] Falha na criptografia — enviando sem criptografia:', e);
      return data;
    }
  };

  return { encryptPayload };
})();

const PortalVidaLivreApi = (() => {
  const API_BASE = "/backend/api";
  const STORAGE_KEY = "portal-vida-livre-csrf";
  let csrfToken = sessionStorage.getItem(STORAGE_KEY) || "";

  const saveCsrfToken = (token) => {
    if (!token) {
      return;
    }

    csrfToken = token;
    sessionStorage.setItem(STORAGE_KEY, token);
  };

  const parseJson = async (response) => {
    let payload = {};

    try {
      payload = await response.json();
    } catch (error) {
      payload = {
        success: false,
        message: "Resposta invalida do servidor.",
        errors: {},
      };
    }

    if (payload?.data?.csrf_token) {
      saveCsrfToken(payload.data.csrf_token);
    }

    if (!response.ok || payload.success === false) {
      throw {
        status: response.status,
        message: payload.message || "Nao foi possivel processar a solicitacao.",
        errors: payload.errors || {},
        data: payload.data || {},
      };
    }

    return payload;
  };

  const request = async (path, options = {}) => {
    const settings = {
      method: options.method || "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(options.headers || {}),
      },
    };

    if (options.csrf) {
      if (!csrfToken) {
        await getCsrfToken();
      }

      settings.headers["X-CSRF-Token"] = csrfToken;
    }

    if (options.body !== undefined) {
      settings.headers["Content-Type"] = "application/json";
      const bodyToSend = settings.method === "POST"
        ? await HybridCrypto.encryptPayload(options.body)
        : options.body;
      settings.body = JSON.stringify(bodyToSend);
    }

    let response;

    try {
      response = await fetch(`${API_BASE}/${path}`, settings);
    } catch (error) {
      throw {
        status: 0,
        message: "Nao foi possivel conectar ao servidor.",
        errors: {},
        data: {},
      };
    }

    return parseJson(response);
  };

  const getCsrfToken = async (force = false) => {
    if (csrfToken && !force) {
      return csrfToken;
    }

    const response = await request("csrf.php");
    saveCsrfToken(response.data.csrf_token || "");

    return csrfToken;
  };

  return {
    request,
    get: (path) => request(path),
    post: (path, body, options = {}) =>
      request(path, { ...options, method: "POST", body }),
    getCsrfToken,
    saveCsrfToken,
  };
})();
