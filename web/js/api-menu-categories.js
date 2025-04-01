$(function () {
    let token = $('meta[name="csrf-token"]').attr("content"),
        $formContainer = $('.js-form-container'),
        $catContainer = $('.js-categories-container'),
        $subContainer = $('.js-subcategories-container'),
        loadForm = function (endpoint, id, menuId, parentId) {
            $formContainer.addClass('load');
            $.post('/mobile-category/' + endpoint + '-form', {
                id: id,
                menu_id: menuId,
                parent_id: parentId,
                _csrf: token
            }, function (res) {
                $formContainer.removeClass('load');
                $formContainer.html(res);

                $('.js-' + endpoint + '-save').on('click', function () {
                    $.post('/mobile-category/' + endpoint + '-save', {
                        id: $('input[name=id]', $formContainer).val(),
                        title: $('input[name=title]', $formContainer).val(),
                        target: $('input[name=target]', $formContainer).val(),
                        params: $('input[name=params]', $formContainer).val(),
                        menu_id: $('input[name=menu_id]', $formContainer).val(),
                        parent_id: $('input[name=parent_id]', $formContainer).val(),
                        _csrf: token
                    },function (){
                        location.reload();
                    });
                });
            });
        },
        loadCategories = function (menuId) {
            $catContainer.addClass('load');
            $.post('/mobile-category/category', {
                menu_id: menuId,
                _csrf: token
            }, function (res) {
                $catContainer.removeClass('load');
                $catContainer.html(res);
            });
        },
        loadSubCategories = function (parentId) {
            $subContainer.addClass('load');
            $.post('/mobile-category/subcategory', {
                parent_id: parentId,
                _csrf: token
            }, function (res) {
                $subContainer.removeClass('load');
                $subContainer.html(res);
            });
        };

    $('.js-add-menu').on('click', function () {
        $catContainer.html('');
        $subContainer.html('');
        loadForm('menu');
    });

    $('.js-menu').on('click', function () {
        let menuId = $(this).data('id');
        $subContainer.html('');
        loadCategories(menuId);
        loadForm('menu', menuId);
    });

    $(document).on('click', '.js-add-category', function () {
        let menuId = $(this).data('menuId');
        let parentId = $(this).data('parentId');
        loadForm('category', 0, menuId, parentId);
    });

    $(document).on('click', '.js-category', function () {
        let parentId = $(this).data('id');
        $subContainer.html('');
        loadSubCategories(parentId);
        loadForm('category', parentId);
    });

    $(document).on('click', '.js-add-subcategory', function () {
        let menuId = $(this).data('menuId');
        let parentId = $(this).data('parentId');
        loadForm('category', menuId, parentId);
    });

    $(document).on('click', '.js-subcategory', function () {
        let id = $(this).data('id');
        loadForm('category', id);
    });

    $(document).on('click', '.list-group-item-action', function () {
        let $this = $(this);
        $this.siblings().removeClass('active');
        $this.addClass('active');
    });

    $(document).on('click', '.js-category-delete', function () {
        let $this = $(this);
        $.post('/mobile-category/category-delete', {
            id: $this.data('id'),
            _csrf: token
        },function (){
            location.reload();
        });
    });
});
