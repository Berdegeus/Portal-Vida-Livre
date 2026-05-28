async function gerarChaveSessao() {
    const chave = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
    );

    const chaveExportada = await crypto.subtle.exportKey('raw', chave);
    const chaveHex = Array.from(new Uint8Array(chaveExportada))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');

    console.log('Chave de sessão gerada:', chaveHex);
    return chave;
}

gerarChaveSessao();