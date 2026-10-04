(() => {
    const localHosts = ['localhost', '127.0.0.1', '[::1]'];

    if (!localHosts.includes(window.location.hostname)) {
        return;
    }

    let initialVersion;

    const checkForChanges = async () => {
        try {
            const response = await fetch(`/dev-reload.php?ts=${Date.now()}`, {
                cache: 'no-store'
            });
            const data = await response.json();

            if (initialVersion === undefined) {
                initialVersion = data.lastModified;
                return;
            }

            if (data.lastModified !== initialVersion) {
                window.location.reload();
            }
        } catch (error) {
            // El servidor puede reiniciarse mientras se edita; se reintentara.
        }
    };

    checkForChanges();
    window.setInterval(checkForChanges, 1000);
})();
