function getMarketId(){
    return document.getElementById('js-market-id').value;
}

function checkAmazonDataForNullQty(hash) {
    // Декодируем base64
    var decodedData = atob(hash);
    // Превращаем строку в объект
    var dataObject = JSON.parse(decodedData);

    // Массив для хранения продуктов с quantity = 0
    let nullQtyItems = [];

    // Проходим по всем заказам
    for (let orderId in dataObject.orders) {
        let order = dataObject.orders[orderId];

        // Проходим по продуктам в заказе
        order.products.forEach(product => {
            if (product.quantity === 0) {
                nullQtyItems.push(product); // Добавляем продукт с quantity = 0 в массив
            }
        });
    }

    // Если есть товары с quantity = 0, выводим сообщение
    if (nullQtyItems.length > 0) {
        // Выводим все найденные товары с quantity = 0
        console.table(nullQtyItems);

        let message = "Внимание! Найдены товары с нулевым количеством.";

        // Формируем список с товарами
        let itemList = "<ul>";
        nullQtyItems.forEach(item => {
            itemList += `<li>${item.title} (QTY: ${item.quantity}, UPC: ${item.upc})</li>`;
        });
        itemList += "</ul>";

        $("#js-check-amazon-null-qty-info")
            .html(message + itemList)
            .removeClass("hidden")
            .addClass("visible");

        return true;
    } else return false;
}

// Показать оверлей с прогресс-баром
function showProgressBar() {
    $('#progress-bar-wrapper').css('display', 'flex');
    // Заблокировать прокрутку страницы body
    $('body').css('overflow', 'hidden');
}

//обработка кнопки #js-overlay-cancel
$('#js-overlay-cancel').click(function() {
    hideProgressBar();
})

$('#js-overlay-close').click(function () {
    //перезагрузка страницы
    window.location.reload();
});

// Функция для обновления прогресс-бара
function updateProgressBar(order, percentComplete, text, currentOrder, totalOrders) {
    console.log(percentComplete, text, currentOrder, totalOrders);
    // Обновляем ширину прогресс-бара
    $('#progress-bar').css('width', percentComplete + '%');
    // Формируем текст для отображения
    var progressBarText = currentOrder + '/' + totalOrders;
    // Обновляем текст прогресс-бара с указанием номера текущего заказа
    $('#progress-bar').text(progressBarText);
}

// Скрыть оверлей с прогресс-баром
function hideProgressBar() {
    $('#progress-bar-wrapper').hide();
    // Разблокировать прокрутку страницы body
    $('body').css('overflow', 'auto');
}

//будем глобально хранить данные для деления в магазине Амазон
//для передачи в разные методы
var amazonPartitionPackageData = [];

$('#js-post-orders').on('click', async function(e) {
    e.preventDefault();
    //блокируем кнопку
    $(this).attr('disabled', 'disabled');
    //скрываем кнопку деления
    $('#js-amazon-pp-block').css('display', 'none');
    //чистим блок ошибок
    $('#js-amazon-error-pp').empty();
    //скрываем блок js-amazon-not-found-pp
    $('#js-amazon-not-found-pp').css('display', 'none');
    //убираем заливку строк в таблице
    $('#js-check-products table tr').css('background', 'unset');
    //показываем overlay для запроса
    $.LoadingOverlay("show");
    //очищаем ошибки и скрываем блок js-redeem-error
    $('#js-redeem-error-list').html('');
    $('#js-redeem-error').css('display', 'none');

    var dataKeySelectProduct = $('#js-check-products').yiiGridView('getSelectedRows');
    var dataProduct = $('#js-textarea-post-orders').val();
    var bankcardNumber = document.getElementById('js-bankcard-number').value;
    var marketId = document.getElementById('js-market-id').value;

    // получаем id корзины
    var cartElement = document.getElementById('js-cart-id');
    if (cartElement) {
        var cartId = cartElement.value;
    }

    var account = document.getElementById('js-account').value;

    // если получили id корзины
    if (cartId) {
        console.log("Cart id: " + cartId);
        //сохраняем хеш в localstorage конкретной корзины если он заполнен
        if (dataProduct) {
            localStorage.setItem('product-cart-data-' + marketId + '-' + cartId, dataProduct);
        }
    } else {
        console.log("Cart id: not found");
        //сохраняем хеш в localstorage если он заполнен
        if (dataProduct) {
            localStorage.setItem('product-data-' + marketId, dataProduct);
        }
    }

    //при нажатии на кнопку js-post-orders сохраняем выбранные продукты
    localStorage.setItem('product-id-' + marketId, JSON.stringify(dataKeySelectProduct));

    var baseUrl = '/mass-order/check-redeemed-product/';
    var baseData = {
        'product_id' : dataKeySelectProduct.toString(),
        'data_product' : dataProduct,
        'bankcard_number' : bankcardNumber,
        'market_id' : marketId,
        'account': account
    }

    if (cartId) {
        baseUrl = '/mass-order-cart/check-redeemed-product/';
        baseData['cart_id'] = cartId;
    }

    //делаем запрос
    $.ajax({
        'type' : 'POST',
        'url' : baseUrl,
        'dataType' : 'json',
        'data' : baseData,
        success: async function(data) {
            $.LoadingOverlay("hide"); // Скрыть загрузочный оверлей

            let hasNullQtyItems = checkAmazonDataForNullQty(dataProduct);

            if (hasNullQtyItems) {
                console.log("Обнаружены товары с нулевым количеством!");
                return;
            } else {
                console.log("Все товары имеют корректное количество.");
            }

            if (data && data.error) {
                alert(data.error);
                console.error(data);
                //разблокируем кнопку
                $('#js-post-orders').removeAttr('disabled');
                return;
            }

            // если придет не пустой partitionPackages или addPartitionPackages
            if (
                (data && data.partitionPackages && Object.keys(data.partitionPackages).length > 0) ||
                (data && data.addPartitionPackages && Object.keys(data.addPartitionPackages).length > 0)
            ) {
                amazonPartitionPackageData = data;
                console.log(amazonPartitionPackageData);
                // Отображаем кнопку деления
                $('#js-amazon-pp-block').css("display", "block");
                // Если она заблокирована, то разблокируем
                $('#js-amazon-partition-package-qty').prop('disabled', false);
                // Выход из функции, т.к. разносить пока нельзя
                return;
            }

            if (data && data.orderNotFound) {
                let errorDiv = $('#js-amazon-not-found-pp');
                let message = '';
                errorDiv.empty();

                // Флаг для проверки наличия данных
                let returnFlag = false;

                Object.entries(data.orderNotFound).forEach(([source, orders]) => {
                    if (Object.keys(orders).length > 0) {
                        returnFlag = true;

                        if (source === 'usmall') {
                            message += '<u>Внимание! Со стороны USmall не удалось найти следующие заказы/товары:</u><br><br>';

                            Object.entries(orders).forEach(([orderId, items]) => {
                                message += "<strong>" + orderId +"</strong><br>";
                                let counter = 1;

                                Object.values(items).forEach(function (item) {
                                    if (item.name) {
                                        message += counter + ". " + item.name + "<br>";
                                        counter++;
                                    }
                                });

                                message += '<br>';
                            });
                        } else if (source === 'amazon') {
                            message += '<u>Внимание! Со стороны Amazon не получили данные для товаров:</u><br><br>';

                            orders.forEach(item => {
                                message += "<strong>" + item.product_id + "</strong><br>";
                                message += item.product_name + "<br><br>";
                            });

                            message += '<br>';

                            setColorForRow(orders, '#ffe2e2')
                        }
                    }
                });

                if (returnFlag) {
                    //выводим информацию о выбранных товарах и хеше
                    getQuantityFromCheckBox(dataKeySelectProduct);
                    getQuantityFromHash(dataProduct);
                    //выводим ошибку
                    errorDiv.css("display", "block");
                    errorDiv.append(message);
                    try {
                        await waitForUserAction(); // Ждем действий пользователя
                        // Продолжаем выполнение кода
                        console.log("continue code");
                    } catch (action) {
                        // снимаем фокус с кнопки
                        $('#js-post-orders-cancel').blur();
                        console.log(`Операция отменена.`);
                        $.LoadingOverlay("show");
                        // Удаляем хеш и галочки из localStorage
                        localStorage.removeItem('product-id-' + marketId);
                        localStorage.removeItem('product-data-' + marketId);
                        if (cartId) {
                            localStorage.removeItem('product-cart-data-' + marketId + '-' + cartId);
                        }
                        window.location.reload();
                        return;
                    }
                }
            }

            // Скрываем кнопку деления
            $('#js-amazon-pp-block').css("display", "none");

            if (data && data.orderRedeem) {
                //проверяем что в data.orderRedeem есть данные
                if(Object.keys(data.orderRedeem).length > 0) {
                    //заполняем форму данными обработки
                    orderList(data.orderRedeem);
                    //блокируем кнопку "Закрыть"
                    $('#js-overlay-close').prop('disabled', true);
                    //показать прогресс бар
                    showProgressBar();
                    $('#progress-bar').css('display', 'block');
                    processOrder(data.orderRedeem, marketId);
                } else {
                    console.log(data);
                    alert('Нет товаров для обработки');
                }
            }
        },
        error: function(e) {
            console.log(e);
            alert('Error: ' + e.status + ' ' + e.statusText);
        },
        complete: function() {
            // $.LoadingOverlay("hide"); // Скрыть загрузочный оверлей
            //разблокируем кнопку
            // $('#js-post-orders').removeAttr('disabled');
        }
    });
});

function waitForUserAction() {
    return new Promise((resolve, reject) => {
        // Скрываем кнопку внесения данных
        $('#js-post-orders').attr('disabled', true);
        $('#js-post-orders').closest('p').css('display', 'none');
        // Скрываем кнопку деления
        $('#js-amazon-pp-block').css("display", "none");

        // Показать доп кнопки
        $('#js-post-orders-continue').css("display", "block");
        $('#js-post-orders-cancel').css("display", "block");

        // Установить обработчики событий
        $('#js-post-orders-continue').on('click', function () {
            resolve('continue'); // Нажата кнопка "продолжить"
        });

        $('#js-post-orders-cancel').on('click', function () {
            reject('cancel'); // Нажата кнопка "отмена"
        });
    });
}

function setColorForRow(productsId, color) {
    productsId.forEach(function(item) {
        $('.js-checkbox-' + item.product_id).closest('tr').css('background-color', color);
    });
}

function getQuantityFromCheckBox(data) {
    let selectedQuantities = [];
    let totalPositions = 0;
    let totalQuantity = 0;
    let errorDiv = $('#js-amazon-not-found-pp');
    let message = '';

    data.forEach(function(productId) {
        // Находим ячейку с data-quantity по productId
        let quantityCell = $('.js-checkbox-' + productId);
        if (quantityCell.length) {
            // Извлекаем значение data-quantity
            let quantity = quantityCell.data('quantity');
            selectedQuantities.push({
                productId: productId,
                quantity: quantity
            });

            totalPositions++;
            totalQuantity += quantity;
        }
    });

    message += '<strong>Выбрано позиций: ' + totalPositions + ' Qty: ' + totalQuantity + '</strong><br>';
    errorDiv.append(message);
}

function getQuantityFromHash(hash) {
    let errorDiv = $('#js-amazon-not-found-pp');
    let message = '';

    try {
        let decodedData = atob(hash);
        let jsonData = JSON.parse(decodedData);

        if (jsonData.orders) {
            let totalProducts = 0;
            let totalQuantity = 0;

            Object.values(jsonData.orders).forEach(order => {
                if (order.products) {
                    totalProducts += order.products.length;

                    order.products.forEach(product => {
                        totalQuantity += product.quantity || 0;
                    });
                }
            });

            message += '<strong>Хэш позиций: ' + totalProducts + ' Qty: ' + totalQuantity + '</strong><br><br>';
            errorDiv.append(message);
        } else {
            console.error("В данных отсутствует объект orders");
        }
    } catch (error) {
        console.error("Ошибка обработки данных:", error);
    }
}

// Amazon async деление
$('#js-amazon-partition-package-qty').on('click', async function(e) {
    e.preventDefault();
    //блокируем кнопку деления
    $('#js-amazon-partition-package-qty').prop('disabled', true);

    $.LoadingOverlay("show", {
        text: "Обрабатываем данные...",
        textClass: "custom-overlay-text"
    });

    var errorListPartitionPackages = [];
    var errorListAddPartitionPackages = [];

    let dataKeySelectProduct = $('#js-check-products').yiiGridView('getSelectedRows');
    let marketId =  getMarketId();
    localStorage.setItem('product-id-' + marketId, JSON.stringify(dataKeySelectProduct));

    if (amazonPartitionPackageData['partitionPackages'] && Object.keys(amazonPartitionPackageData['partitionPackages']).length > 0) {
        await getAmazonPartitionPackage(amazonPartitionPackageData['partitionPackages'], "PartitionPackages", errorListPartitionPackages);
    }

    if (amazonPartitionPackageData['addPartitionPackages'] && Object.keys(amazonPartitionPackageData['addPartitionPackages']).length > 0) {
        await getAmazonPartitionPackage(amazonPartitionPackageData['addPartitionPackages'], "addPartitionPackages", errorListAddPartitionPackages);
    }

    async function getAmazonPartitionPackage(amazonPartitionPackageData, type, errorList) {
        let progressText = '';
        let totalPackages = 0;
        let processedPackages = 0;

        if (type === "PartitionPackages") {
            let packages = amazonPartitionPackageData['partitionPackage'];
            let packageIds = amazonPartitionPackageData['partitionPackageId'];
            let qtyProducts = amazonPartitionPackageData['quantity'] || {};

            totalPackages = packageIds.length;
            progressText = 'PartitionPackages обработано: 0/' + totalPackages;
            $.LoadingOverlay("text", progressText);

            while (packageIds.length) {
                let packageId = packageIds.shift();
                let packageProduct = packages[packageId];
                let qtyProduct = qtyProducts[packageId] || 0;

                await ajaxPartitionPackage(packageId, packageProduct, qtyProduct, packages, packageIds, qtyProducts, errorList);
                processedPackages++;
                progressText = 'PartitionPackages обработано: ' + processedPackages + '/' + totalPackages;
                $.LoadingOverlay("text", progressText);
            }
        }

        if (type === "addPartitionPackages") {
            totalPackages = Object.keys(amazonPartitionPackageData).reduce((count, orderId) => count + amazonPartitionPackageData[orderId]['partitionPackageId'].length, 0);
            progressText = 'AddPartitionPackages обработано: 0/' + totalPackages;
            $.LoadingOverlay("text", progressText);
            for (let orderId in amazonPartitionPackageData) {
                if (amazonPartitionPackageData.hasOwnProperty(orderId)) {
                    let packageData = amazonPartitionPackageData[orderId];
                    let packages = packageData['partitionPackage'];
                    let packageIds = packageData['partitionPackageId'];

                    for (let packageId of packageIds) {
                        let packageProduct = packages[packageId];
                        let qtyProducts = packageData['quantity'] || {};
                        let qtyProduct = qtyProducts[packageId] || 0;

                        await ajaxPartitionPackage(packageId, packageProduct, qtyProduct, packages, packageIds, qtyProducts, errorList);
                        processedPackages++;
                        progressText = 'AddPartitionPackages обработано: ' + processedPackages + '/' + totalPackages;
                        $.LoadingOverlay("text", progressText);
                    }
                }
            }
        }
    }

    async function ajaxPartitionPackage(packageId, packageProduct, qtyProduct, packages, packageIds, qtyProducts, errorList) {
        try {
            await $.ajax({
                'type' : 'POST',
                'url' : '/mass-order/partition-packages-qty/' ,
                'dataType' : 'json',
                'data' : {
                    'package_id' : packageId,
                    'package_product' : packageProduct,
                    'quantity_product' : qtyProduct
                },
                success: function(result) {
                    if(result.error){
                        errorList.push(result.error);
                    }
                },
                error: function(e) {
                    console.log(e);
                    errorList.push("Ошибка запроса для package_id: " + packageId);
                }
            })
        } catch (error) {
            console.error("Error:", error.status + " " + error.statusText);
            errorList.push(error.status + " " + error.statusText);
        }
    }

    // Выводим ошибки в errorDiv, если они есть
    if (errorListPartitionPackages.length || errorListAddPartitionPackages.length) {
        let errorDiv = $('#js-amazon-error-pp');
        errorDiv.empty();

        // Объединяем списки ошибок и отображаем их
        const allErrors = errorListPartitionPackages.concat(errorListAddPartitionPackages);
        allErrors.forEach(function(item){
            errorDiv.append("<p>" + item + "</p>");
        });
        $.LoadingOverlay("hide");
        $('#js-post-orders').removeAttr('disabled');
    } else {
        // Если ошибок нет, перезагружаем страницу
        $.LoadingOverlay("text", 'Обработка выполнена успешно. Перезагрузка страницы...');
        window.location.reload()
    }

    // Очищаем amazonPartitionPackageData
    amazonPartitionPackageData = [];
    console.log(amazonPartitionPackageData);

    console.log("Ошибки PartitionPackages:", errorListPartitionPackages);
    console.log("Ошибки AddPartitionPackages:", errorListAddPartitionPackages);
});

var jsOverlayError = []

//функция рекурсивной обработки
async function processOrder(orders, marketId) {
    let savedProductIds = JSON.parse(localStorage.getItem('product-id-' + marketId)) || [];

    for (let [index, order] of Object.entries(orders)) {
        try {
            // Устанавливаем статус заказа
            $('.js-order-status-' + index).addClass('fontBold');
            $('.js-order-status-' + index).text('Статус: Обрабатывается');
            console.log("Process order " + order.external_order_id);
            console.log("Products " + order.product_id);

            await $.ajax({
                url: '/mass-order/redeemed-product',
                method: 'POST',
                'dataType': 'json',
                'data': {
                    'product_id' : order.product_id,
                    'external_order_id' : order.external_order_id,
                    'bankcard_number' : order.bankcard_number,
                    'delivery_cost_buyout_usd' : order.delivery_cost_buyout_usd,
                    'discount' : order.discount,
                    'market_id' : order.market_id,
                    'account' : order.account
                },
                success: function(data) {
                    var defaultStatus = 'Обработан';

                    if (data && (data.exception || data.error)) {
                        if(data.error){
                            $('.js-order-status-' + index).text('Статус: ОШИБКА (' + data.error + ')');
                            defaultStatus = 'ОШИБКА';
                        }
                        if(data.exception) {
                            $('.js-order-status-' + index).text('Статус: ОШИБКА (' + data.exception.message + ')');
                            defaultStatus = 'ОШИБКА';
                        }
                    } else {
                        // Успешный запрос - устанавливаем статус "готов"
                        $('.js-order-status-' + index).text('Статус: готов');
                        $('.js-order-status-' + index).removeClass('fontBold');

                        /// Удаляем обработанный product_id из localStorage
                        savedProductIds = savedProductIds.filter(id => !order.product_id.includes(id));
                    }

                    // Преобразуем index в целое число
                    index = parseInt(index, 10);
                    // Вычисляем процент выполнения на основе индекса заказа и общего количества заказов
                    var percentComplete = ((index + 1) / orders.length) * 100;
                    // Обновляем прогресс-бар с номером текущего заказа и общим количеством заказов
                    updateProgressBar(order.external_order_id, percentComplete, defaultStatus, index + 1, orders.length);

                    // Обновляем localStorage с новыми данными
                    localStorage.setItem('product-id-' + marketId, JSON.stringify(savedProductIds));
                },
                error: function(request, status, error) {
                    console.log(request);
                    // Ошибка запроса - устанавливаем статус "ошибка"
                    $('.js-order-status-' + index).text('Статус: ошибка');
                    $('.js-order-status-' + order.id).removeClass('fontBold');

                    // Преобразуем index в целое число
                    index = parseInt(index, 10);
                    var percentComplete = ((index + 1) / orders.length) * 100;
                    updateProgressBar(order.external_order_id, percentComplete, 'Ошибка', index + 1, orders.length);
                }
            });
        } catch (err) {
            console.error("Error:", err.status + " " + err.statusText);
            jsOverlayError.push(err);
        }
    }

    // Если нет ошибок, то удаляем выбранные позиции из localStorage
    if (jsOverlayError.length === 0) {
        localStorage.removeItem('product-id-' + marketId);
        // Удаляем хеш
        localStorage.removeItem('product-data-' + marketId);

        // получаем id корзины
        var cartElement = document.getElementById('js-cart-id');
        if (cartElement) {
            var cartId = cartElement.value;
        }

        if (cartId) {
            // удаляем хеш корзины
            localStorage.removeItem('product-cart-data-' + marketId + '-' + cartId);
        }
    }

    $('#js-overlay-close').prop('disabled', false);
}

function orderList(orders) {
    console.log(orders);
    // Формируем список заказов и выводим в #js-orders-list
    var html = '';
    for (var i = 0; i < orders.length; i++) {
        var order = orders[i];
        html += '<div style="display: flex; flex-direction: row; justify-content: space-between; align-items: center">';
        html += '<li>' + order.external_order_id + ' (<span>' + (i + 1) + '</span>' + '/' + '<span>' + orders.length + '</span>)' + ' - <span class="js-order-status-' + i + '"> Статус: В очереди на обработку</span></li>';
        html += '</div>';
    }
    $('#js-orders-list').html(html);
}