$(function () {
    let token = $('meta[name="csrf-token"]').attr("content"),
        $container = $('.js-form-container'),
        loadTemplate = function (templateId) {
            $container.addClass('load');
            $.post('/seo-meta/template', {id: templateId, _csrf: token}, function (res) {
                $container.removeClass('load');
                $container.html(res);
                initMainForm();
            });
        },
        loadParam = function (paramId) {
            let $subContainer = $('.js-subform-container');
            $subContainer.addClass('load');
            $.post('/seo-meta/param-items', {
                template_id: $('[name=template_id]').val(),
                id: paramId,
                _csrf: token
            }, function (res) {
                $subContainer.removeClass('load');
                $subContainer.html(res);
                initItemsForm();
            });
        },
        initMainForm = function () {
            $('.js-chosen').select2();

            $('.js-template-save').on('click', function () {
                $container.addClass('load');
                $.post('/seo-meta/template-save', {
                    id: $('[name=template_id]').val(),
                    category_id: $('[name=category_id]').val(),
                    prefix: $('[name=template_prefix]').val(),
                    title: $('[name=template_title]').val(),
                    name: $('[name=template_name]').val(),
                    url: $('[name=template_url]').val(),
                    h1: $('[name=template_h1]').val(),
                    _csrf: token
                }, function () {
                    $container.removeClass('load');
                    $container.html('');
                    $('.list-group-item-action').removeClass('active');
                });
            });

            $('.js-template-delete').on('click', function () {
                $container.addClass('load');
                $.post('/seo-meta/template-delete', {
                    id: $('[name=template_id]').val(),
                    _csrf: token
                }, function () {
                    location.reload();
                });
            });

            $('.js-param').on('click', function (event) {
                if (event.target.nodeName.toLowerCase() !== 'li') return;
                loadParam($(this).data('id'));
            });

            $('.js-param-add').on('click', function () {
                $.post('/seo-meta/param-save', {
                    name: $(this).closest('.input-group').find('input').val(),
                    _csrf: token
                }, function () {
                    loadTemplate($('[name=template_id]').val());
                });
            });

            $('.js-param-edit').on('click', function () {
                let $subContainer = $('.js-subform-container');
                $subContainer.addClass('load');
                $.post('/seo-meta/param', {
                    id: $(this).data('id'),
                    _csrf: token
                }, function (res) {
                    $subContainer.removeClass('load');
                    $subContainer.html(res);
                    initParamForm();
                });
            });
        },
        initParamForm = function () {
            $('.js-param-save').on('click', function () {
                $('.js-subform-container').addClass('load');
                $.post('/seo-meta/param-save', {
                    id: $('[name=param_id]').val(),
                    code: $('[name=param_code]').val(),
                    name: $('[name=param_name]').val(),
                    title: $('[name=param_title]').val(),
                    facet_id: $('[name=param_facet_id]').val(),
                    _csrf: token
                }, function () {
                    loadTemplate($('[name=template_id]').val());
                });
            });
        },
        initItemsForm = function () {
            $('.js-chosen').select2({
                ajax: {
                    url: '/seo-meta/entity-search',
                    data: function (params) {
                        return {
                            query: params.term,
                            model: $('[name=param_code]').val(),
                            param_id: $('[name=param_id]').val()
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results
                        };
                    }
                }
            });

            $('.js-item-add').on('click', function () {
                $('.js-subform-container').addClass('load');
                $.post('/seo-meta/item-save', {
                    seo_meta_param_id: $('[name=param_id]').val(),
                    entity_id: $('[name=param_entity_id]').val(),
                    _csrf: token
                }, function () {
                    loadParam($('[name=param_id]').val());
                });
            });

            $('.js-item').on('click', function (event) {
                if (event.target.nodeName.toLowerCase() !== 'li') return;
                let $supContainer = $('.js-supform-container');
                $supContainer.addClass('load');
                $.post('/seo-meta/item', {
                    id: $(this).data('id'),
                    _csrf: token
                }, function (res) {
                    $supContainer.removeClass('load');
                    $supContainer.html(res);
                    initItemForm();
                });
            });

            $('.js-item-link').on('click', function () {
                let $this = $(this);
                $.post('/seo-meta/item-link', {
                    template_id: $('[name=template_id]').val(),
                    id: $this.data('id'),
                    _csrf: token
                }, function (res) {
                    if (res) {
                        $this.addClass('btn-outline-warning');
                        $this.removeClass('btn-outline-dark');
                    } else {
                        $this.removeClass('btn-outline-warning');
                        $this.addClass('btn-outline-dark');
                    }
                }, 'json');
            });

            $('.js-item-text-add').on('click', function () {
                $('.js-subform-container').addClass('load');
                let param_id = $('[name=param_id]').val();
                $.post('/seo-meta/item-text-save', {
                        id: $('[name=item_id]').val(),
                        seo_meta_param_id: param_id,
                        template_id: $('[name=template_id]').val(),
                        entity_id: $('[name=param_entity_id]').val(),
                        _csrf: token
                    }, function () {
                        loadParam(param_id)
                    }
                );
            });
        },
        initItemForm = function () {
            $('.js-item-save').on('click', function () {
                let $supContainer = $('.js-supform-container');
                $supContainer.addClass('load');
                $.post('/seo-meta/item-save', {
                    id: $('[name=item_id]').val(),
                    seo_meta_param_id: $('[name=item_seo_meta_param_id]').val(),
                    entity_id: $('[name=item_entity_id]').val(),
                    orig: $('[name=item_orig]').val(),
                    rus: $('[name=item_rus]').val(),
                    url: $('[name=item_url]').val(),
                    _csrf: token
                }, function () {
                    $supContainer.removeClass('load');
                });
            });
        };

    $('.js-template').on('click', function () {
        loadTemplate($(this).data('id'));
    });

    $(document).on('click', '.list-group-item-action', function () {
        let $this = $(this);
        $this.siblings().removeClass('active');
        $this.addClass('active');
    });
});
