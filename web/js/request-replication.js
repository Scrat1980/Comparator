$('.js-button-initial').on('click', function(e){
    /* Get the text field */
    var copyText = document.getElementById("js-request-initial");
    var tmp = $("<textarea>");
    $("body").append(tmp);
    tmp.val($(copyText).text()).select();
    document.execCommand("copy");
    tmp.remove();

});

$('.js-button-product').on('click', function(e){
    /* Get the text field */
    var copyText = document.getElementById("js-request-product");
    var tmp = $("<textarea>");
    $("body").append(tmp);
    tmp.val($(copyText).text()).select();
    document.execCommand("copy");
    tmp.remove();

});
$('.js-button-split').on('click', function(e){
    /* Get the text field */
    var copyText = document.getElementById("js-request-split");
    var tmp = $("<textarea>");
    $("body").append(tmp);
    tmp.val($(copyText).text()).select();
    document.execCommand("copy");
    tmp.remove();

});
$('.js-button-order-package').on('click', function(e){
    /* Get the text field */
    var copyText = document.getElementById("js-request-order-package");
    var tmp = $("<textarea>");
    $("body").append(tmp);
    tmp.val($(copyText).text()).select();
    document.execCommand("copy");
    tmp.remove();

});
$('.js-button-order-package-product').on('click', function(e){
    /* Get the text field */
    var copyText = document.getElementById("js-request-order-package-product");
    var tmp = $("<textarea>");
    $("body").append(tmp);
    tmp.val($(copyText).text()).select();
    document.execCommand("copy");
    tmp.remove();

});
$('.js-button-order').on('click', function(e){
    /* Get the text field */
    var copyText = document.getElementById("js-request-order");
    var tmp = $("<textarea>");
    $("body").append(tmp);
    tmp.val($(copyText).text()).select();
    document.execCommand("copy");
    tmp.remove();

});
$('.js-button-order-all').click(function() {
    var textarea = document.getElementById('js-textarea-request-query');
    var tempTextarea = document.createElement('textarea');
    tempTextarea.value = textarea.value;
    document.body.appendChild(tempTextarea);
    tempTextarea.select();
    document.execCommand('copy');
    document.body.removeChild(tempTextarea);
});


let copy = (word) => {
    return $('.js-button-' + word).on('click', function(e){
        /* Get the text field */
        var copyText = document.getElementById("js-request-" + word);
        var tmp = $("<textarea>");
        $("body").append(tmp);
        tmp.val($(copyText).text()).select();
        document.execCommand("copy");
        tmp.remove();
    });
};

// Should correspond with values in _query_panel.php
copy('mass-order-history-query');
copy('order-query');
copy('product-query');
copy('all-query');
copy('order-ids');
