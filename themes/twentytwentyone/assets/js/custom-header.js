document.addEventListener("DOMContentLoaded", () => {
    let navContainer = document.querySelector('.nav-container');
    let body = document.querySelector('body');
    let hamburgerBtn = document.querySelector('.hamburger-icon');
    let headerOvarlay = document.querySelector('.header-ovarlay');
    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', () => {
            navContainer.classList.toggle('menu-active');
            body.classList.toggle('mobile-menu-open');
            hamburgerBtn.classList.toggle('active');
            headerOvarlay.classList.toggle('active');
        });
    } else {
        console.log('Element not found!');
    }
    if (headerOvarlay) {
        headerOvarlay.addEventListener('click', () => {
            navContainer.classList.remove('menu-active');
            body.classList.remove('mobile-menu-open');
            hamburgerBtn.classList.remove('active');
            headerOvarlay.classList.remove('active');
        });
    } else {
        console.log('Element not found! else');
    }

    // sub-menu 
    let mainMenu = document.querySelectorAll('.menu-item-has-children');
    let subMenu = document.querySelectorAll('.menu-item-has-children > ul');
    mainMenu.forEach(item => {
        let subMenu = item.querySelector('ul');
        let subspan = document.createElement('span');
        subspan.className = 'submenu-toggle';
        item.appendChild(subspan);
        item.insertBefore(subspan, subMenu);
        subspan.addEventListener('click', () => {
            subMenu.classList.toggle('open');
            subspan.classList.toggle('active');
            console.log("subMenu clicked")
        });
    });


    // Select all parent menu items
    let menuItems = document.querySelectorAll('.nav-dropdown.mobile-only .menu-item.has-children');

    menuItems.forEach(item => {
        let link = item.querySelector(':scope > a');
        let subMenu = item.querySelector(':scope > .sub-menu');

        if (subMenu) {

            // 👉 Create Back Button
            let backBtn = document.createElement('li');
            backBtn.classList.add('back-btn');
            backBtn.innerHTML = `<span><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.828 6.778H16V8.778H3.828L9.192 14.142L7.778 15.556L0 7.778L7.778 0L9.192 1.414L3.828 6.778Z" fill="black"/></svg></span>`;

            // 👉 Add back button at top of submenu
            subMenu.prepend(backBtn);

            // 👉 Open submenu
            link.addEventListener('click', function (e) {
                e.preventDefault();
                item.classList.add('open-submenu');
            });

            // 👉 Close submenu (Back button)
            backBtn.addEventListener('click', function () {
                item.classList.remove('open-submenu');
            });
        }
    });
});