
<?php
class MatchCRUD {

    private $pdo;

    public function __construct($dbConfig) {
        try {
            // Создание подключения к базе данных
            $this->pdo = new PDO("pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}", $dbConfig['user'], $dbConfig['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Could not connect to the database: " . $e->getMessage());
        }
    }

    public function getTeams() {
        $stmt = $this->pdo->query("SELECT team_id, name FROM teams ORDER BY team_id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function teamExists($team_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM teams WHERE team_id = :team_id");
        $stmt->execute(['team_id' => $team_id]);
        return $stmt->fetchColumn() > 0;
    }


    public function validateGoals($goals) {
        if(!empty($goals)){
            return is_numeric($goals) && intval($goals) >= 0;
        }else{
            return true;
        }
    }

    public function validateMatchDate($match_date){
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $match_date)) {
            return "Invalid date format. Please enter a date in YYYY-MM-DD format.\n";
        }
        try {
            $date = new DateTime($match_date);
        } catch (Exception $e) {
            return "Invalid date. Please enter a valid date.";
        }
        $current_date = date('Y-m-d');
        if ($match_date > $current_date) {
            return "The match date cannot be in the future.\n";
        }
        return true;
    }

    // Создание нового матча
    public function create($team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament) {

        $team_1_id = trim($team_1_id);
        $team_2_id = trim($team_2_id);
        $team_1_goals = trim($team_1_goals);
        $team_2_goals = trim($team_2_goals);
        $match_date = trim($match_date);
        $tournament = trim($tournament);
    
        if (empty($team_1_id) || empty($team_2_id) || empty($match_date) || empty($tournament)) {
            throw new InvalidArgumentException("All fields must be filled.");
        }
    
        if (!preg_match('/^\d+$/', $team_1_id) || !preg_match('/^\d+$/', $team_2_id)) {
            throw new InvalidArgumentException("ID cannot be other than a number");
        }
    
        if (!$this->teamExists($team_1_id) || !$this->teamExists($team_2_id)) {
            throw new InvalidArgumentException("One or both teams do not exist.");
        }
    
        if ($team_1_id == $team_2_id) {
            throw new InvalidArgumentException("Team 1 and Team 2 must be different.");
        }
    
        if (!$this->validateGoals($team_1_goals) || !$this->validateGoals($team_2_goals)) {
            throw new InvalidArgumentException("Goals must be non-negative integers.");
        }
    
        $dateValidation = $this->validateMatchDate($match_date);
        if ($dateValidation !== true) {
            throw new InvalidArgumentException($dateValidation);
        }
    
        if (strlen($tournament) > 32 || !preg_match('/[a-zA-Zа-яА-ЯёЁ]/', $tournament)) {
            throw new InvalidArgumentException("Tournament name cannot consist only of special characters or exceed 32 characters.");
        }
    
        // Проверка на существование записи с теми же командами и датой
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM matches 
                                     WHERE match_date = :match_date 
                                       AND ((team_1_id = :team1_id AND team_2_id = :team2_id) 
                                         OR (team_1_id = :team2_id AND team_2_id = :team1_id))");
        $stmt->execute([
            'match_date' => $match_date,
            'team1_id' => $team_1_id,
            'team2_id' => $team_2_id
        ]);
    
        if ($stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException("A match between these teams on this date already exists.");
        }
    
        $stmt = $this->pdo->prepare("INSERT INTO matches (team_1_id, team_2_id, team_1_goals, team_2_goals, match_date, tournament) 
                                     VALUES (:team1_id, :team2_id, :team1_goals, :team2_goals, :match_date, :tournament)");
        $stmt->execute([
            'team1_id' => $team_1_id,
            'team2_id' => $team_2_id,
            'team1_goals' => intval($team_1_goals),
            'team2_goals' => intval($team_2_goals),
            'match_date' => $match_date,
            'tournament' => $tournament
        ]);
    }

    // Получение всех матчей
    public function retrieveAll() {
        $stmt = $this->pdo->query("SELECT 
                                    m.match_id,
                                    m.team_1_id,
                                    m.team_2_id,
                                    t1.name AS team_1_name, 
                                    t2.name AS team_2_name,
                                    m.team_1_goals,
                                    m.team_2_goals,
                                    m.match_date,
                                    m.tournament
                                FROM matches m
                                JOIN teams t1 ON m.team_1_id = t1.team_id
                                JOIN teams t2 ON m.team_2_id = t2.team_id;");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получение матча по ID
    public function retrieve($id) {
        if (!filter_var($id, FILTER_VALIDATE_INT)){
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT m.match_id,
                                    m.team_1_id,
                                    m.team_2_id,
                                    t1.name AS team_1_name, 
                                    t2.name AS team_2_name,
                                    m.team_1_goals,
                                    m.team_2_goals,
                                    m.match_date,
                                    m.tournament
                                FROM matches m
                                JOIN teams t1 ON m.team_1_id = t1.team_id
                                JOIN teams t2 ON m.team_2_id = t2.team_id
                                WHERE match_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Обновление информации о матче

    public function update($id, $team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament) {
        // Получение текущих данных записи
        $currentData = $this->retrieve($id);
    
        // Очистка данных
        $team_1_id = trim($team_1_id);
        $team_2_id = trim($team_2_id);
        $team_1_goals = trim($team_1_goals);
        $team_2_goals = trim($team_2_goals);
        $match_date = trim($match_date);
        $tournament = trim($tournament);
    
        // Если поле пустое, использовать текущее значение из базы
        $team_1_id = !empty($team_1_id) ? $team_1_id : $currentData['team_1_id'];
        $team_2_id = !empty($team_2_id) ? $team_2_id : $currentData['team_2_id'];
        $team_1_goals = !empty($team_1_goals) ? $team_1_goals : $currentData['team_1_goals'];
        $team_2_goals = !empty($team_2_goals) ? $team_2_goals : $currentData['team_2_goals'];
        $match_date = !empty($match_date) ? $match_date : $currentData['match_date'];
        $tournament = !empty($tournament) ? $tournament : $currentData['tournament'];


        //Проверка на корректность team_id
        if(!preg_match('/^\d+$/',$team_1_id)||!preg_match('/^\d+$/',$team_2_id)){
            throw new InvalidArgumentException("ID cannot be other than a number");
        }
        if (!$this->teamExists($team_1_id) || !$this->teamExists($team_2_id)) {
            throw new InvalidArgumentException("One or both teams do not exist.");
        }

        // Проверка на идентичность команд
        if (!empty($team_1_id) && !empty($team_2_id) && $team_1_id == $team_2_id) {
            throw new InvalidArgumentException("Team 1 and Team 2 must be different.");
        }
    
        // Проверка на корректность голов (целые числа >= 0)
        if (!$this->validateGoals($team_1_goals) || !$this->validateGoals($team_2_goals)) {
            throw new InvalidArgumentException("Goals must be non-negative integers.");
        }
    
        // Проверка даты на корректный формат
        $dateValidation = $this->validateMatchDate($match_date);
        if ($dateValidation !== true) {
            throw new InvalidArgumentException($dateValidation);
        }
    
        // Проверка длины турнира
        if (strlen($tournament) > 32 || !preg_match('/[a-zA-Zа-яА-ЯёЁ]/',$tournament)) {
            throw new InvalidArgumentException("Tournament name cannot consist only of special characters or exceed 32 characters.");
        }
    
        
    
        // Подготовка и выполнение SQL-запроса
        $stmt = $this->pdo->prepare("UPDATE matches 
                                     SET team_1_id = :team_1_id, team_2_id = :team_2_id, team_1_goals = :team_1_goals, 
                                         team_2_goals = :team_2_goals, match_date = :match_date, tournament = :tournament 
                                     WHERE match_id = :id");
        $stmt->execute([
            'id' => $id,
            'team_1_id' => $team_1_id,
            'team_2_id' => $team_2_id,
            'team_1_goals' => $team_1_goals,
            'team_2_goals' => $team_2_goals,
            'match_date' => $match_date,
            'tournament' => $tournament
        ]);
    }
   

    
    // Удаление матча по ID
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM matches WHERE match_id = :id");
        $stmt->execute(['id' => $id]);
    }

    // Удаление нескольких матчей
    public function deleteMany($ids) {
        if (empty($ids)) return;

        // Создаем строку с параметрами для запроса
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM matches WHERE match_id IN ($placeholders)");
        $stmt->execute($ids);
    }

    public function displayTable(array $data){

        if (empty($data)) {
            echo "No data available.\n";
            return;
        }

        if (!is_array(reset($data))){
            $data = [$data];
        }
        // Получаем заголовки
        $headers = array_keys(reset($data));

        // Рассчитываем ширину колонок для выравнивания
        $columnWidths = [];
        foreach ($headers as $header) {
            $columnWidths[$header] = strlen($header);
        }

        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                $columnWidths[$key] = max($columnWidths[$key], strlen((string)$value));
            }
        }

        // вывод заголовков
        echo "\n";
        foreach ($headers as $header) {
            echo str_pad($header, $columnWidths[$header] + 3);
        }
        echo "\n" . str_repeat('-', array_sum($columnWidths) + count($columnWidths) * 2) . "\n";

        // вывод строк данных
        foreach ($data as $row) {
            foreach ($headers as $header) {
                echo str_pad($row[$header] ?? '', $columnWidths[$header] + 3);
            }
            echo "\n";
        }
    }
    
}

function main() {

    // Конфигурация базы данных
    $dbConfig = [
        'host' => 'dpg-cup84nhopnds7391v3s0-a.oregon-postgres.render.com',
        'port' => '5432',
        'dbname' => 'db_gmwg',
        'user' => 'zhambal',
        'password' => 'ocwwkPJQD2SkdTxmbOn9FZjckfe5OwYZ'
    ];

    $crud = new MatchCRUD($dbConfig);

    while (true) {
        echo "\n1. Create Match\n2. Retrieve All Matches\n3. Retrieve Match\n4. Update Match\n5. Delete Match\n6. Delete Many Matches\n7. Exit\n";

        $choice = readline("Choose an option: ");

        switch ($choice) {
            case '1':
                // Вывод списка команд
                echo "\nAvailable teams:\n";
                $teams = $crud->getTeams(); // Метод, который возвращает список команд
                foreach ($teams as $team) {
                    echo "ID: {$team['team_id']} - Name: {$team['name']}\n";
                }
                while(true){
                    $team_1_id = readline("\nEnter team 1 ID: ");
                    $team_2_id = readline("Enter team 2 ID: ");
                    $team_1_goals = readline("Enter team 1 goals(default: 0): ");
                    $team_2_goals = readline("Enter team 2 goals(default: 0): ");
                    $match_date = readline("Enter match date (YYYY-MM-DD): ");
                    $tournament = readline("Enter tournament name: ");

                    try {
                        $crud->create($team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament);
                        echo "Match created successfully.\n";
                        break;
                    } catch (InvalidArgumentException $e) {
                        echo "Error: " . $e->getMessage() . "\n";
                        $retry = readline("Do you want to try again? (yes/no): ");
                        if (strtolower($retry) !== 'yes') {
                            echo "Update canceled.\n";
                            break;
                        }
                    }
                }
                
                break;

            case '2':
                $matches = $crud->retrieveAll();
                $crud->displayTable($matches);
                
                // foreach ($matches as $match) {
                //     echo "Match ID: {$match['match_id']}\nTeam 1 ID: {$match['team_1_id']} - Team 2 ID: {$match['team_2_id']}\nTeam 1 name: {$match['team_1_name']} - Team 2 name: {$match['team_2_name']}\nTeam 1 Goals: {$match['team_1_goals']} - Team 2 Goals: {$match['team_2_goals']}\nMatch Date: {$match['match_date']}\nTournament: {$match['tournament']}\n\n";
                //     echo str_repeat('-', 50) . PHP_EOL;
                // }
                break;

            case '3':
                $id = readline("Enter match ID: ");
                if ($match = $crud->retrieve($id)) {
                    $crud->displayTable($match);
                    //echo "\nMatch ID: {$match['match_id']}\nTeam 1 ID: {$match['team_1_id']} - Team 2 ID: {$match['team_2_id']}\nTeam 1 name: {$match['team_1_name']} - Team 2 name: {$match['team_2_name']}\nTeam 1 Goals: {$match['team_1_goals']} - Team 2 Goals: {$match['team_2_goals']}\nMatch Date: {$match['match_date']}\nTournament: {$match['tournament']}\n\n";
                } else {
                    echo "\nMatch not found.\n";
                }
                break;

            case '4':
                $id = readline("Enter match ID to update: ");
                if ($match = $crud->retrieve($id)) {
                    echo "\nMatch ID: {$match['match_id']}\nTeam 1 ID: {$match['team_1_id']} - Team 2 ID: {$match['team_2_id']}\nTeam 1 name: {$match['team_1_name']} - Team 2 name: {$match['team_2_name']}\nTeam 1 Goals: {$match['team_1_goals']} - Team 2 Goals: {$match['team_2_goals']}\nMatch Date: {$match['match_date']}\nTournament: {$match['tournament']}\n\n";
                    while(true){
                        $team_1_id = readline("Enter new team 1 ID: ");
                        $team_2_id = readline("Enter new team 2 ID: ");
                        $team_1_goals = readline("Enter new team 1 goals: ");
                        $team_2_goals = readline("Enter new team 2 goals: ");
                        $match_date = readline("Enter new match date (YYYY-MM-DD): ");
                        
                        $tournament = readline("Enter new tournament name: ");
                        try {
                            $crud->update($id, $team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament);
                            echo "Match updated.\n";
                            break;
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                            $retry = readline("Do you want to try again? (yes/no): ");
                            if (strtolower($retry) !== 'yes') {
                                echo "Update canceled.\n";
                                break;
                            }
                        }
                    }
                }else {
                    echo "Match not found.\n";
                }
                    
                break;

            case '5':
                $id = readline("Enter match ID to delete: ");
                if ($crud->retrieve($id)) {
                    $crud->delete($id);
                    echo "Match deleted.\n";
                } else {
                    echo "Match not found.\n";
                }
                break;

            case '6':
                $idsInput = readline("Enter IDs separated by commas: "); 
                if (!empty(trim($idsInput))) { 
                    // Преобразуем строку в массив целых чисел 
                    $ids = array_unique(array_map('trim', explode(',', trim($idsInput)))); 

                    $existingIds = [];
                    $nonExistingIds = [];

                    foreach ($ids as $id) {

                        // Проверяем, существует ли запись с текущим ID
                        if ($crud->retrieve($id)) {
                            $existingIds[] = $id;
                        } else {
                            $nonExistingIds[] = $id; // Добавляем в массив отсутствующих ID
                        }
                    }

                    if (empty($existingIds)) {
                        echo "No matches found with the given IDs.\n";
                    } else {
                        // Выводим отсутствующие ID, если они есть
                        if (!empty($nonExistingIds)) {
                            echo "The following IDs were not found: " . implode(', ', $nonExistingIds) . "\n";
                        }
                        // Удаляем существующие записи
                        $crud->deleteMany($existingIds);
                        echo "Matches with IDs " . implode(', ', $existingIds) . " have been deleted.\n";
                    }
                }else { 
                    echo "No IDs provided.\n"; 
                } 
                break;

            case '7':
                exit;

            default:
                echo "Invalid choice.\n";
        }
    }
}
main();