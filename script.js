$(function() {
    $('.top a').click(function() {
        $('.top a').removeClass('active');
        $('#options, #emulator, #history, .success').hide();
        $(this).addClass('active');
        $('#' + $(this).data('r')).show();
        if ($(this).data('r') == 'results') getHistory();
        return false;
    });

    $('form').submit(function() {
        var el = $(this);
        var button = el.find('input[type="submit"]'),
            range_min = el.find('input[name="min"]'),
            range_max = el.find('input[name="max"]'),
            success = el.parent().find('.success');

        button.attr('disabled', 'disabled');

        var params = {
            min: range_min.val(),
            max: range_max.val()
        };
        $.post(el.attr('action'), params).done(function(data) {
            var jdata = JSON.parse(data);
            if (jdata.success == 1) {
                range_min.val(jdata.content.range.min);
                range_max.val(jdata.content.range.max);
                if (jdata.content.questions) {
                    success.removeClass('error').hide();
                    questionsTable(jdata.content.questions);
                } else {
                    success.removeClass('error').show().text(jdata.content.message);
                }
            } else {
                success.addClass('error').show().text(jdata.content);
            }
        }).fail(function() {
            alert('Ошибка: не удалось получить ответ сервера!');
        }).always(function() {
            button.removeAttr('disabled');
            return false;
        });
        return false;
    });
});

function questionsTable(jdata) {
    var el = $('#result');
    el.find('tr:not(.header)').remove();
    $.each(jdata, function (i, v) {
        el.show().append('<tr><td>' + v.nomer + '</td>' +
        '<td>' + v.question_id + '</td>' +
        '<td>' + v.count_used + '</td>' +
        '<td>' + v.complexity + '</td>' +
        '</td><td>' + (v.result == 1 ? 'Да' : 'Нет') + '</td></tr>');
    });
}

function getHistory() {
    var el = $('#history');
    el.find('tr:not(.header)').remove();
    el.show();
    $.get('emulator.php?r=history', function(data) {
        var jdata = JSON.parse(data);
        $.each(jdata.content, function (i, v) {
            el.append('<tr><td>' + v.nomer + '</td>' +
            '<td>' + v.intellect + '</td>' +
            '<td>' + v.complexity + '</td>' +
            '</td><td>' + v.result[0] + ' из ' + v.result[1] + '</td></tr>');
        });
    });
}