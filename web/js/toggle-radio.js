let App = {
    initialize: () => {
        App.highlightSelectedMarketLink();
        App.marketLinkList.addEventListener('click', App.processor);
    },
    allMarketsId: 0,
    highlightSelectedMarketLink: () => {
        let selectedMarketId = document.querySelector('#selected_market').dataset.value * 1
            || 1
        ;
        if (selectedMarketId === App.allMarketsId) {
            return;
        }
        let marketLink = document.querySelector('#a-' + selectedMarketId);

        marketLink.setAttribute(
            'style',
            'border-color: #eeeeee #eeeeee #ddd; '
            + 'text-decoration: none; '
            + 'background-color: #eeeeee;'
        );

    },
    marketLinkList: document.querySelector('.email-parse-index'),
    processor: (e) => {
        if (e.target.nodeName !== 'A') {
            return;
        }
        App.selectDropdownMarket(e);
        App.clearSearchConditions();
        // App.checkHiddenRadioMarket(e)
        App.clearHighlightingMarketLink();
        App.searchOnMarketClick();
    },
    clearSearchConditions: () => {
        document.querySelector('#external_number').value = '';
        document.querySelector('#operation').value = 'all';
    },
    clearHighlightingMarketLink: () => {
        let markets = document.querySelectorAll('.email-parse-index a');
        for (let i of markets) {
            i.setAttribute('style', '');
        }
    },
    checkHiddenRadioMarket: (e) => {
        let id = e.target
            .getAttribute('id')
            .replace(/a-/, '');

        let buttons = document.querySelectorAll('[name=\'market_id\']');
        for (let i of buttons) {
            i.checked = false;
        }
        let button = document.querySelector('[name=\'market_id\'][value=\'' + id + '\']');
        button.checked = true;

    },
    selectDropdownMarket: (e) => {
        let id = e.target
            .getAttribute('id')
            .replace(/a-/, '');

        document.querySelector('#market_id').value = id;
    },
    submitButton: document.querySelector('[type=submit]'),
    searchOnMarketClick: () => {
        App.submitButton.click();
    }
};

document.addEventListener('DOMContentLoaded', App.initialize);

