<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Тестовое</title>
    <link rel="stylesheet" href="style.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
    <script src="script.js"></script>
</head>
<body>

<h1>Тестовое</h1>

<div class="top">
    <a href="#" class="active" data-r="options">Настройка теста</a>
    <a href="#" data-r="emulator">Эмулятор теста</a>
    <a href="#" data-r="results">История результатов</a>
</div>

<div id="options">
    <form name="options" action="emulator.php?r=options" method="post">
        <b>Сложность вопросов:</b>
        <br/>
        от: <input name="min" type="number" value="0">
        до: <input name="max" type="number" value="100">
        <input type="submit" value="Сохранить">
    </form>
    <div class="success"></div>
</div>

<div id="emulator">
    <form name="emulator" action="emulator.php?r=emulator" method="post">
        <b>Уровень интеллекта респондента:</b>
        <br/>
        от: <input name="min" type="number" value="0">
        до: <input name="max" type="number" value="100">
        <input type="submit" value="Запуск теста">
    </form>
    <div class="success"></div>
    <table id="result">
        <tr class="header">
            <th>Номер</th>
            <th>ID</th>
            <th>Встречался</th>
            <th>Сложность вопроса</th>
            <th>Был ли дан правильный ответ</th>
        </tr>
    </table>
</div>

<table id="history">
    <tr class="header">
        <th>Номер</th>
        <th>Интеллект тестируемого</th>
        <th>Диапазон сложности вопросов</th>
        <th>Результат тестирования</th>
    </tr>
</table>

</body>
</html>