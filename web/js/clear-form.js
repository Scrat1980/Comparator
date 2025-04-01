$('.js-clear-search').on('click', function(e) {
    e.preventDefault();
    $('.form-control').val('');
    $('#search-form').submit();
});
