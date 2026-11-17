const themeControl = document.querySelector('[data-theme-control]');
const themeToggle = document.querySelector('[data-theme-toggle]');
const themePanel = document.querySelector('#selectorTema');
const themeOptions = document.querySelectorAll('[data-theme-option]');
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
const themeStorageKey = 'cineVerseTheme';
const allowedThemes = ['system', 'light', 'dark'];
let themePreference = 'system';

try {
    const storedTheme = localStorage.getItem(themeStorageKey);
    if (allowedThemes.includes(storedTheme)) {
        themePreference = storedTheme;
    }
} catch {
    // El selector sigue funcionando si el navegador bloquea el almacenamiento.
}

const applyTheme = () => {
    document.documentElement.dataset.theme = themePreference;
    const isDark = themePreference === 'dark' || (themePreference === 'system' && systemTheme.matches);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', isDark ? '#171719' : '#f2f5f6');
    themeOptions.forEach((option) => {
        option.setAttribute('aria-pressed', String(option.dataset.themeOption === themePreference));
    });
    if (themeToggle) {
        const themeLabels = { system: 'sistema', light: 'claro', dark: 'oscuro' };
        const themeLabel = `Cambiar apariencia. Tema actual: ${themeLabels[themePreference]}`;
        themeToggle.setAttribute('aria-label', themeLabel);
        themeToggle.title = themeLabel;
    }
};

applyTheme();
if (themeControl && themeToggle && themePanel) {
    themeControl.hidden = false;
    themePanel.addEventListener('toggle', (event) => {
        themeToggle.setAttribute('aria-expanded', String(event.newState === 'open'));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && themePanel.matches(':popover-open')) {
            event.preventDefault();
            themePanel.hidePopover();
            themeToggle.focus({ preventScroll: true });
        }
    });
    themeOptions.forEach((option) => {
        option.addEventListener('click', () => {
            themePreference = allowedThemes.includes(option.dataset.themeOption) ? option.dataset.themeOption : 'system';
            applyTheme();
            try {
                localStorage.setItem(themeStorageKey, themePreference);
            } catch {
                // La preferencia se mantiene durante esta visita.
            }
            themePanel.hidePopover();
            themeToggle.focus({ preventScroll: true });
        });
    });
}

systemTheme.addEventListener('change', applyTheme);
window.addEventListener('storage', (event) => {
    if (event.key === themeStorageKey || event.key === null) {
        themePreference = allowedThemes.includes(event.newValue) ? event.newValue : 'system';
        applyTheme();
    }
});

const menuToggle = document.querySelector('[data-menu-toggle]');
const menuPanel = document.querySelector('[data-menu-panel]');

if (menuToggle && menuPanel) {
    const desktopMenu = window.matchMedia('(min-width: 1024px)');

    const setMenuOpen = (isOpen) => {
        menuPanel.hidden = !isOpen && !desktopMenu.matches;
        menuToggle.setAttribute('aria-expanded', String(isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
    };

    menuToggle.hidden = false;
    setMenuOpen(false);

    menuToggle.addEventListener('click', () => {
        setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
    });

    menuPanel.addEventListener('click', (event) => {
        if (!desktopMenu.matches && event.target.closest('a')) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !desktopMenu.matches && menuToggle.getAttribute('aria-expanded') === 'true') {
            setMenuOpen(false);
            menuToggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!desktopMenu.matches && !menuPanel.contains(event.target) && !menuToggle.contains(event.target)) {
            setMenuOpen(false);
        }
    });

    desktopMenu.addEventListener('change', () => setMenuOpen(false));
}

document.querySelectorAll('[data-notification]').forEach((notification) => {
    const closeButton = notification.querySelector('[data-close-notification]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let closeTimer;
    let isClosing = false;

    const pauseClose = () => window.clearTimeout(closeTimer);

    const closeNotification = () => {
        if (isClosing) {
            return;
        }

        isClosing = true;
        pauseClose();

        if (notification.contains(document.activeElement)) {
            document.querySelector('[data-notification-focus-target]')?.focus({ preventScroll: true });
        }

        notification.classList.add('is-closing');
        window.setTimeout(() => notification.remove(), reduceMotion.matches ? 0 : 300);
    };

    const scheduleClose = () => {
        pauseClose();
        if (!isClosing && !notification.matches(':hover') && !notification.contains(document.activeElement)) {
            closeTimer = window.setTimeout(closeNotification, 9000);
        }
    };

    closeButton?.addEventListener('click', closeNotification);
    notification.addEventListener('mouseenter', pauseClose);
    notification.addEventListener('mouseleave', scheduleClose);
    notification.addEventListener('focusin', pauseClose);
    notification.addEventListener('focusout', (event) => {
        if (!notification.contains(event.relatedTarget)) {
            scheduleClose();
        }
    });

    scheduleClose();
});

// Cubre tanto errores de red futuros como imágenes fallidas antes de cargar este archivo.
document.querySelectorAll('[data-product-image]').forEach((productImage) => {
    const imageFallback = productImage.parentElement.querySelector('[data-product-fallback]');
    const showImageFallback = () => {
        productImage.hidden = true;
        if (imageFallback) {
            imageFallback.hidden = false;
        }
    };

    productImage.addEventListener('error', showImageFallback, { once: true });
    if (productImage.complete && productImage.naturalWidth === 0) {
        showImageFallback();
    }
});

// Estas ayudas complementan la validación de los controladores PHP.
document.querySelectorAll('[data-password-toggle]').forEach((passwordToggle) => {
    const passwordInput = document.getElementById(passwordToggle.getAttribute('aria-controls'));
    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    passwordToggle.hidden = false;
    passwordToggle.addEventListener('click', () => {
        const isVisible = passwordInput.type === 'password';
        passwordInput.type = isVisible ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(isVisible));
        passwordToggle.setAttribute('aria-label', isVisible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        passwordToggle.textContent = isVisible ? 'Ocultar' : 'Mostrar';
    });
});

document.querySelectorAll('.account-form').forEach((accountForm) => {
    const countryField = accountForm.querySelector('[data-country-field]');
    const postalField = accountForm.querySelector('[data-postal-field]');
    const postalHint = accountForm.querySelector('[data-postal-hint]');
    if (!countryField || !postalField || !postalHint) {
        return;
    }

    const updatePostalPattern = () => {
        const isPortugal = countryField.value === 'Portugal';
        postalField.pattern = isPortugal ? '[0-9]{4}|[0-9]{7}' : '[0-9]{5}';
        postalField.maxLength = isPortugal ? 7 : 5;
        postalHint.textContent = isPortugal ? '4 o 7 dígitos, sin guion.' : '5 dígitos, sin espacios.';
    };

    countryField.addEventListener('change', updatePostalPattern);
    updatePostalPattern();
});

// La confirmación es una ayuda; el servidor vuelve a validar sesión, importes y stock.
document.querySelectorAll('[data-confirm-purchase]').forEach((purchaseForm) => {
    purchaseForm.addEventListener('submit', (event) => {
        if (!window.confirm('¿Quieres finalizar la compra con los artículos y el total indicados?')) {
            event.preventDefault();
        }
    });
});

// Controles opcionales: el formulario sigue funcionando sin JavaScript.
document.querySelectorAll('[data-cart-quantity]').forEach((quantityForm) => {
    const quantityInput = quantityForm.querySelector('input[name="cantidad"]');
    if (!(quantityInput instanceof HTMLInputElement)) {
        return;
    }
    const stepButtons = quantityForm.querySelectorAll('[data-quantity-step]');
    const updateStepButtons = () => {
        const quantity = Number(quantityInput.value);
        stepButtons.forEach((stepButton) => {
            const nextQuantity = quantity + Number(stepButton.dataset.quantityStep);
            stepButton.disabled = quantityInput.disabled || !Number.isInteger(quantity)
                || nextQuantity < Number(quantityInput.min) || nextQuantity > Number(quantityInput.max);
        });
    };
    stepButtons.forEach((stepButton) => {
        stepButton.hidden = false;
        stepButton.addEventListener('click', () => {
            const nextQuantity = Number(quantityInput.value) + Number(stepButton.dataset.quantityStep);
            if (Number.isInteger(nextQuantity) && nextQuantity >= Number(quantityInput.min) && nextQuantity <= Number(quantityInput.max)) {
                quantityInput.value = String(nextQuantity);
                quantityInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });
    quantityInput.addEventListener('input', updateStepButtons);
    updateStepButtons();
});
