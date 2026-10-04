document.addEventListener('DOMContentLoaded', () => {
	const usuario = document.getElementById('usuario');
	const password = document.getElementById('password');
	const togglePassword = document.querySelector('.toggle-password');

	if (usuario) usuario.focus();
	if (password && togglePassword) {
		togglePassword.addEventListener('click', () => {
			const mostrar = password.type === 'password';
			password.type = mostrar ? 'text' : 'password';
			togglePassword.textContent = mostrar ? 'Ocultar' : 'Mostrar';
			togglePassword.setAttribute('aria-pressed', String(mostrar));
		});
	}

	// Small enhancement: submit on Ctrl+Enter in password field
	if (password) {
		password.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
				e.preventDefault();
				password.form && password.form.submit();
			}
		});
	}
});

