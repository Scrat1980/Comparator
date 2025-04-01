let visible = localStorage.getItem('visible');
let markets = document.querySelector('.nav-tabs');

if (visible === null || visible === 'block') {
    markets.style.display = 'block';
} else {
    markets.style.display = 'none';
}

document.querySelector('[data-role=js-hide-markets]')
    .addEventListener('click', () => {
        let markets = document.querySelector('.nav-tabs');
        let vis= markets.style.display === 'none'
            ? 'block'
            : 'none'
        ;
        markets.style.display = vis;
        localStorage.setItem('visible', vis);

    })
;

