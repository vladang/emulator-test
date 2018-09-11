<?php

class Emulator
{
    protected $db_host = 'localhost';
    protected $db_user = 'root';
    protected $db_pass = '';
    protected $db_name = 'test2';

    function __construct()
    {
        $this->sql = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
        if ($this->sql->connect_error) exit('Не удалось подключиться к MySQL: ' . $this->db->connect_error);
        $this->sql->set_charset('utf8');
    }

    // Роутинг
    public function route($r)
    {
        $post = empty($_POST) ? 0 : $_POST;

        switch ($r) {
            case 'options':
                return $this->saveOptionsTest($post);
                break;
            case 'emulator':
                return $this->emulator($post);
                break;
            case 'history':
                return $this->history();
                break;
            default:
                exit('Route error');
                break;
        }
    }

    // Создает новый тест в таблице "test"
    protected function saveOptionsTest($data)
    {
        $range = $this->getRange($data);
        if (!$range) return $this->json('Сложность вопроса не корректна (допустимо от 0 до 100)', 0);

        $range_sql = $this->sql->escape_string($range['min'] . '-' . $range['max']);

        // Если у последнего теста нет результатов то редактируем (чтобы не плодить пустых тестов), иначе создаем новый
        $test = $this->sql->query("SELECT id FROM test t LEFT JOIN results r ON r.id_test = t.id WHERE r.id_test IS NULL ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if (empty($test['id'])) {
            $this->sql->query("INSERT INTO test SET complexity = '$range_sql'");
        } else {
            $this->sql->query("UPDATE test SET complexity = '$range_sql' WHERE id = " . (int)$test['id']);
        }

        return $this->json(
            array(
                'range' => $range,
                'message' => 'Настройки успешно сохранены, теперь вы можете запустить эмулятор теста.'
            )
        );
    }

    // История результатов
    protected function history()
    {
        $history = array();
        $respondents = $this->sql->query("SELECT * FROM respondents");
        while ($respondent = $respondents->fetch_assoc()) {

            $stat = $this->sql->query("
              SELECT count(r.result) AS cnt, sum(r.result) AS summa, t.complexity
              FROM results r
              LEFT JOIN test t ON t.id = r.id_test
              WHERE r.id_respond = " . $respondent['id'])->fetch_assoc();

            $history[] = array(
                'nomer' => $respondent['id'],
                'intellect' => $respondent['intellect'],
                'complexity' => $stat['complexity'],
                'result' => array($stat['summa'], $stat['cnt'])
            );
        }
        return $this->json($history);
    }

    // Запуск эмулятора теста
    protected function emulator($data)
    {
        $intellect = $this->getRange($data);
        if (!$intellect) return $this->json('Уровень интеллекта респондента введен не корректно (допустимо от 0 до 100)', 0);

        // Получаем последний созданный тест
        $test = $this->sql->query("SELECT * FROM test ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if (!$test) return $this->json('Перед запуском эмулятора, нужно сохранить настройки теста', 0);

        // Добавляем респондента
        $range_sql = $this->sql->escape_string($intellect['min'] . '-' . $intellect['max']);
        $this->sql->query("INSERT INTO respondents SET intellect = '$range_sql'");
        $id_respond = $this->sql->insert_id;

        // Сложность теста
        $complexity = explode('-', $test['complexity']);
        $complexity = array('min' => $complexity[0], 'max' => $complexity[1]);

        // Максимальное количество использования вопроса
        $max_used = $this->sql->query("SELECT max(count_used) FROM questions")->fetch_assoc();
        $max_used = $max_used['max(count_used)']+1;

        $qu_max = 0;
        $questions_array = array();
        // Получаем все вопросы
        $questions = $this->sql->query("SELECT * FROM questions");
        while ($question = $questions->fetch_assoc()) {
            // Формируем диапазон по частоте использования и добавляем в массив $questions_array
            $qu_min = $qu_max+1;
            $qu_max = $qu_min+floor($max_used/($question['count_used']+1)); // Диапазон будет выше у реже используемых
            $questions_array[$question['id']] = array(
                'qu_min' => $qu_min,
                'qu_max' => $qu_max,
                'count_used' => $question['count_used']
            );
        }

        $random_qu = array();
        // Эмулируем 40 вопросов
        for ($i=1; $i<=40; $i++) {
            // Получаем случайный вопрос с результатом ответа и массив оставшихся вопросов
            $random = $this->getRandomQuestion($questions_array, $qu_max, $complexity, $intellect);
            // Результаты ответа добаляем в массив $random_qu
            $random_qu[] = array(
                'nomer' => $i,
                'question_id' => $random['qu_rand'],
                'result' => $random['result'],
                'complexity' => $random['complexity'],
                'count_used' => $random['count_used']
            );

            $this->sql->query("UPDATE questions SET count_used = count_used+1 WHERE id = " . (int)$random['qu_rand']);

            $this->sql->query("INSERT INTO results SET
                id_test = '" . (int)$test['id'] . "',
                id_question = '" . (int)$random['qu_rand'] . "',
                id_respond = '" . (int)$id_respond . "',
                result = '" . (int)$random['result'] . "'
            ");

            if (empty($random['questions'])) break;
            $questions_array = $random['questions'];
            $qu_max = $random['qu_max'];
        }

        return $this->json(
            array(
                'range' => $intellect,
                'questions' => $random_qu
            )
        );
    }

    /*
      Возвращает случайный вопрос, ответ респондента и сложность вопроса
      $questions - Массив вопросов
      $max - Максимальное число диапазона частоты использования
      $complexity - Диапазон сложности
      $intellect - диапазон интеллекта
    */
    protected function getRandomQuestion($questions, $max, $complexity, $intellect)
    {
        $rand = rand(1, $max);

        list($qu_max, $qu_rand, $count_used) = 0;

        foreach ($questions as $k => $v) {
            if ($rand >= $v['qu_min'] && $rand <= $v['qu_max']) {
                $qu_rand = $k;
                $count_used = $v['count_used'];
                break;
            }
        }

        unset($questions[$qu_rand]);

        foreach ($questions as $k => $v) {
            $qu_min = $qu_max+1;
            $qu_max = $qu_min+($v['qu_max']-$v['qu_min']);
            $questions[$k] = array(
                'qu_min' => $qu_min,
                'qu_max' => $qu_max,
                'count_used' => $v['count_used']
            );
        }

        $complexity = rand($complexity['min'], $complexity['max']);
        $intellect = rand($intellect['min'], $intellect['max']);

        $result = rand(0, $complexity+$intellect) <= $intellect ? 1 : 0;
        if ($intellect == $complexity) $result = rand(0, 1);
        if ($intellect == 0 && $complexity > 0) $result = 0;
        if ($intellect == 100 && $complexity < 100) $result = 1;

        return array(
            'qu_max' => $qu_max,
            'qu_rand' => $qu_rand,
            'complexity' => $complexity,
            'result' => $result,
            'count_used' => $count_used,
            'questions' => $questions
        );
    }

    // Валидность диапазона (min - max)
    protected function getRange($range)
    {
        $min = isset($range['min']) ? (int)$range['min'] : 0;
        $max = isset($range['max']) ? (int)$range['max'] : 0;

        if ($min <= $max && $min >= 0 && $max <= 100) {
            return array('min' => $min, 'max' => $max);
        }
        return false;
    }

    protected function json($content, $success = 1)
    {
        $data = array('success' => $success, 'content' => $content);
        exit(json_encode($data));
    }
}

$test = new Emulator();

if (isset($_GET['r'])) $test->route($_GET['r']);