// "Get Started FREE" menus: only one open at a time; close on outside click or Escape.
const menus = [...document.querySelectorAll('[data-get-started]')];

const closeMenus = (except = null) => {
    menus.forEach((menu) => {
        if (menu !== except) menu.open = false;
    });
};

menus.forEach((menu) => {
    menu.addEventListener('toggle', () => {
        if (menu.open) closeMenus(menu);
    });
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Node)) return;
    if (!menus.some((menu) => menu.contains(event.target))) closeMenus();
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const open = menus.find((menu) => menu.open);
    if (open) {
        open.open = false;
        open.querySelector('summary')?.focus();
    }
});
