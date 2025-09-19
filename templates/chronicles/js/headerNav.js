const header = document.querySelector('header');
const headerWrapper = document.querySelector('.header__wrapper');
const goToNewsBtn = document.querySelector('.goto-news-btn');
const burgerBtn = document.querySelector('.hamburger');
const nav = document.querySelector('.nav');
const navItems = nav.querySelectorAll('.nav__item');

// Shrink header on scroll
const handleHeader = () => {
	window.addEventListener('scroll', () => {
		if (window.scrollY > 50) {
			header.classList.add('shrink');
		} else {
			header.classList.remove('shrink');
		}
	});
};

const handleMobileNav = () => {
	nav.classList.toggle('is-active');
	burgerBtn.classList.toggle('is-active');
	document.documentElement.classList.toggle('scroll-lock');
	document.body.classList.toggle('scroll-lock');

	if (nav.classList.contains('is-active')) {
		// headerWrapper.style.backdropFilter = 'none';
		header.classList.add('nav-open');
	} else {
		// headerWrapper.style.backdropFilter = 'blur(4.2px)';
		header.classList.remove('nav-open');
	}

	navItems.forEach(item => {
		item.addEventListener('click', () => {
			nav.classList.remove('is-active');
			burgerBtn.classList.remove('is-active');
			document.documentElement.classList.remove('scroll-lock');
			document.body.classList.remove('scroll-lock');
			headerWrapper.style.backdropFilter = 'blur(4.2px)';
		});
	});
};

const scrollToNews = () => {
	window.scrollTo({
		top: 800,
	});
};

document.addEventListener('DOMContentLoaded', handleHeader);
burgerBtn.addEventListener('click', handleMobileNav);

// goToNewsBtn.addEventListener('click', scrollToNews);
