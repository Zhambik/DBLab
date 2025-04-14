<?php
class PlayerCRUD {

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

    // public function validatePosition($position) {
    //     $validPositions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
    //     return in_array($position, $validPositions);
    // } 

    public function selectPlayerPosition(): string {
        $positions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
    
        while (true) {
            echo "\nSelect player position:\n";
            foreach ($positions as $index => $pos) {
                echo ($index + 1) . ". $pos\n";
            }
    
            $choice = readline("Enter position number (1-4): ");
    
            if (ctype_digit($choice) && (int)$choice >= 1 && (int)$choice <= count($positions)) {
                return $positions[(int)$choice - 1];
            }
    
            echo "Invalid input. Please enter a number between 1 and 4.\n";
        }
    }
    

    public function validateName($value, $fieldName) {
        if (empty($value)) {
            throw new InvalidArgumentException("$fieldName cannot be empty.");
        }
        if (preg_match('/[0-9]/', $value)) {
            throw new InvalidArgumentException("$fieldName cannot contain numbers.");
        }
        if ($value !== trim($value)) {
            throw new InvalidArgumentException("$fieldName cannot start or end with a space.");
        }        
        if (!preg_match('/^[A-Za-z]+(?:[ -][A-Za-z]+)*$/', $value)) {
            throw new InvalidArgumentException("$fieldName can only contain English letters, single spaces or hyphens between words.");
        }        
        return true;
    }

    public function validateBirthDate($birth_date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            throw new InvalidArgumentException("Invalid date format. Please enter a date in YYYY-MM-DD format.");
        }
    
        try {
            $birthDate = DateTime::createFromFormat('Y-m-d', $birth_date);
            $errors = DateTime::getLastErrors();
    
            // Проверка на реальные ошибки парсинга даты (например, 2002-02-30)
            if ($birthDate === false || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
                throw new InvalidArgumentException("The provided birth date is not a valid calendar date.");
            }
    
            $currentDate = new DateTime();
            $age = $currentDate->diff($birthDate)->y;
    
            if ($birthDate > $currentDate) {
                throw new InvalidArgumentException("The birth date cannot be in the future.");
            }
    
            if ($age < 16) {
                throw new InvalidArgumentException("The player must be at least 16 years old.");
            }
    
        } catch (Exception $e) {
            throw new InvalidArgumentException("Invalid date: " . $e->getMessage());
        }
    
        return true;
    }
    


    // Создание нового игрока
    public function create($name, $surname, $birth_date, $country, $position, $team_id) {
        // Приводим к нижнему регистру, как в индексе
        $nameLower = strtolower($name);
        $surnameLower = strtolower($surname);
        $countryLower = strtolower($country);

        // Проверка уникальности
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM players WHERE LOWER(name) = :name AND LOWER(surname) = :surname AND birth_date = :birth_date AND LOWER(country) = :country");
        $stmt->execute([
            ':name' => $nameLower,
            ':surname' => $surnameLower,
            ':birth_date' => $birth_date,
            ':country' => $countryLower
        ]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            throw new InvalidArgumentException("Player with the same name, surname, birth date, and country already exists.");
        }

        $name = trim($name);
        $surname = trim($surname);
        $birth_date = trim($birth_date);
        $country = trim($country);
        $position = trim($position);
        $team_id = trim($team_id);

        // Проверка на пустые поля
        if (empty($name) || empty($surname) || empty($birth_date) || empty($country) || empty($position) || empty($team_id)) {
            throw new InvalidArgumentException("All fields must be filled.");
        }  

        // Проверка имени
        $this->validateName($name, "Name"); 

        // Проверка фамилии
        $this->validateName($surname, "Surname");

        // Проверка страны
        $this->validateName($country, "Country");

        // Проверка даты рождения
        $this->validateBirthDate($birth_date);

        // // Проверка позиции
        // if (!$this->validatePosition($position)) {
        //     throw new InvalidArgumentException("Invalid position. Allowed positions: Goalkeeper, Defender, Midfielder, Forward.");
        // }

        // Проверка team_id
        if (!preg_match('/^\d+$/', $team_id)) {
            throw new InvalidArgumentException("Team ID must be a number.");
        }
        if (!$this->teamExists($team_id)) {
            throw new InvalidArgumentException("Team does not exist.");
        }

        // Вставка данных в базу
        $stmt = $this->pdo->prepare("INSERT INTO players (name, surname, birth_date, country, position, team_id) 
                                     VALUES (:name, :surname, :birth_date, :country, :position, :team_id)");
        $stmt->execute([
            'name' => $name,
            'surname' => $surname,
            'birth_date' => $birth_date,
            'country' => $country,
            'position' => $position,
            'team_id' => $team_id
        ]);
    }

    // Получение всех игроков
    public function retrieveAll() {
        $stmt = $this->pdo->query("SELECT 
                                    p.player_id,
                                    p.name,
                                    p.surname,
                                    p.birth_date,
                                    p.country,
                                    p.position,
                                    p.team_id,
                                    t.name AS team_name
                                FROM players p
                                JOIN teams t ON p.team_id = t.team_id
                                ORDER BY p.player_id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получение игрока по ID
    public function retrieve($id) {
        if (!filter_var($id, FILTER_VALIDATE_INT)){
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT 
                                    p.player_id,
                                    p.name,
                                    p.surname,
                                    p.birth_date,
                                    p.country,
                                    p.position,
                                    p.team_id,
                                    t.name AS team_name
                                FROM players p
                                JOIN teams t ON p.team_id = t.team_id
                                WHERE player_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Обновление информации об игроке
    public function update($id, $name, $surname, $birth_date, $country, $position, $team_id) {
        $currentData = $this->retrieve($id);

        
        $name = trim($name);
        $surname = trim($surname);
        $birth_date = trim($birth_date);
        $country = trim($country);
        $position = trim($position);
        $team_id = trim($team_id);

        // Если поле пустое, использовать текущее значение из базы
        $name = $name !== '' ? $name : $currentData['name'];
        $surname = $surname !== '' ? $surname : $currentData['surname'];
        $birth_date = $birth_date !== '' ? $birth_date : $currentData['birth_date'];
        $country = $country !== '' ? $country : $currentData['country'];
        $position = $position !== '' ? $position : $currentData['position'];
        $team_id = $team_id !== '' ? $team_id : ($currentData['team_id'] !== null ? $currentData['team_id'] : null);

        //  // Приводим к нижнему регистру, как в индексе
        $nameLower = strtolower($name);
        $surnameLower = strtolower($surname);
        $countryLower = strtolower($country);

        // Проверка уникальности для обновления
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM players 
            WHERE LOWER(name) = :name 
            AND LOWER(surname) = :surname 
            AND birth_date = :birth_date 
            AND LOWER(country) = :country 
            AND player_id <> :id
        ");
        $stmt->execute([
            ':name' => $nameLower,
            ':surname' => $surnameLower,
            ':birth_date' => $birth_date,
            ':country' => $countryLower,
            ':id' => $id
        ]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            throw new InvalidArgumentException("A player with the same name, surname, birth date, and country already exists.");
        }
    
        // Проверка имени
        $this->validateName($name, "Name");

        // Проверка фамилии
        $this->validateName($surname, "Surname");

        // Проверка страны
        $this->validateName($country, "Country");

        // Проверка даты рождения
        $this->validateBirthDate($birth_date);

        // Проверка на корректность team_id
        if (!preg_match('/^\d+$/', $team_id)) {
            throw new InvalidArgumentException("Team ID must be a number.");
        }

        if (!$this->teamExists($team_id)) {
            throw new InvalidArgumentException("Team does not exist.");
        }

        // if (!$this->validatePosition($position)) {
        //     throw new InvalidArgumentException("Invalid position.");
        // }

        // Подготовка и выполнение SQL-запроса
        $stmt = $this->pdo->prepare("UPDATE players 
                                     SET name = :name, surname = :surname, birth_date = :birth_date, 
                                         country = :country, position = :position, team_id = :team_id 
                                     WHERE player_id = :id");
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'surname' => $surname,
            'birth_date' => $birth_date,
            'country' => $country,
            'position' => $position,
            'team_id' => $team_id
        ]);
    }

    // Удаление игрока по ID
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM players WHERE player_id = :id");
        $stmt->execute(['id' => $id]);
    }

    // Удаление нескольких игроков
    public function deleteMany($ids) {
        if (empty($ids)) return;

        // Создаем строку с параметрами для запроса
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM players WHERE player_id IN ($placeholders)");
        $stmt->execute($ids);
    }

    public function displayTable(array $data) {
        if (empty($data)) {
            echo "No data available.\n";
            return;
        }

        if(!is_array(reset($data))){
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
        'host' => 'dpg-cvgbj5popnds73bh2ci0-a.oregon-postgres.render.com',
        'port' => '5432',
        'dbname' => 'db_rsn9',
        'user' => 'zhambal',
        'password' => 'NrVwNsRTvQdqXZ31s5ZfAgHkZDUa3Bqg'
    ];

    $crud = new PlayerCRUD($dbConfig);

    while (true) {
        echo "\n1. Create Player\n2. Retrieve All Players\n3. Retrieve Player\n4. Update Player\n5. Delete Player\n6. Delete Many Players\n7. Exit\n";

        $choice = readline("Choose an option: ");
        $teams = $crud->getTeams();
        switch ($choice) {
            case '1':
                // // Вывод списка команд
                // echo "\nAvailable teams:\n";
                // $teams = $crud->getTeams();
                // foreach ($teams as $team) {
                //     echo "ID: {$team['team_id']} - Name: {$team['name']}\n";
                // }
                
                while(true) {
                    while(true) {
                        $name = readline("\nEnter player name: ");
                        try {
                            $crud->validateName($name, "Name");
                            break;
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                        }
                    }
                    while(true){
                        $surname = readline("Enter player surname: ");
                        try {
                            $crud->validateName($surname, "Surname"); 
                            break;
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                        }
                    }
                    while(true){
                        $birth_date = readline("Enter player birth date (YYYY-MM-DD): ");
                        try {
                            $crud->validateBirthDate($birth_date); 
                            break;
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                        }
                    }    
                    while(true){
                        $country = readline("Enter player country: ");
                        try {
                            $crud->validateName($country, "Country"); 
                            break;
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                        }
                    }
                    $position = $crud->selectPlayerPosition();
                    while (true) {
                        $team_id = readline("Enter team ID (or '?' to list teams): ");
                    
                        if ($team_id === '?') {
                            echo "\nAvailable teams:\n";
                            foreach ($teams as $team) {
                                echo "ID: {$team['team_id']} - Name: {$team['name']}\n";
                            }
                            echo "\n";
                            continue;
                        }
                    
                        // Проверка, что ID — число и существует
                        $valid_team = false;
                        foreach ($teams as $team) {
                            if ((int)$team_id === (int)$team['team_id']) {
                                $valid_team = true;
                                break;
                            }
                        }
                    
                        if ($valid_team) {
                            break;
                        } else {
                            echo "Invalid team ID. Try again or type '?' to list teams.\n";
                        }
                    }
                    

                    try {
                        $crud->create($name, $surname, $birth_date, $country, $position, $team_id);
                        echo "Player created successfully.\n";
                        break;
                    } catch (InvalidArgumentException $e) {
                        echo "Error: " . $e->getMessage() . "\n";
                        $retry = readline("Do you want to try again? (yes/no): ");
                        if (strtolower($retry) !== 'yes') {
                            echo "Creation canceled.\n";
                            break;
                        }
                    }
                }
                break;

            case '2':
                $players = $crud->retrieveAll();
                $crud->displayTable($players);
                break;

            case '3':
                $id = readline("Enter player ID: ");
                if ($player = $crud->retrieve($id)) {
                    $crud->displayTable($player);
                } else {
                    echo "\nPlayer not found.\n";
                }
                break;

                case '4':
                    $id = readline("Enter player ID to update: ");
                    if ($player = $crud->retrieve($id)) {
                        $crud->displayTable($player); 
                        echo "\n";               
                        // Обновление имени игрока
                        while (true) {
                            $name = readline("Enter new name (leave empty to keep current: {$player['name']}): ");
                            if (!empty($name)) {
                                try {
                                    $crud->validateName($name, "Name");
                                    break;
                                } catch (InvalidArgumentException $e) {
                                    echo "Error: " . $e->getMessage() . "\n";
                                }
                            } else {
                                break;
                            }
                        }
                
                        // Обновление фамилии игрока
                        while (true) {
                            $surname = readline("Enter new surname (leave empty to keep current: {$player['surname']}): ");
                            if (!empty($surname)) {
                                try {
                                    $crud->validateName($surname, "Surname");
                                    break;
                                } catch (InvalidArgumentException $e) {
                                    echo "Error: " . $e->getMessage() . "\n";
                                }
                            } else {
                                break;
                            }
                        }
                
                        // Обновление даты рождения игрока
                        while (true) {
                            $birth_date = readline("Enter new birth date (YYYY-MM-DD, leave empty to keep current: {$player['birth_date']}): ");
                            if (!empty($birth_date)) {
                                try {
                                    $crud->validateBirthDate($birth_date);
                                    break;
                                } catch (InvalidArgumentException $e) {
                                    echo "Error: " . $e->getMessage() . "\n";
                                }
                            } else {
                                break;
                            }
                        }
                
                        // Обновление страны игрока
                        while (true) {
                            $country = readline("Enter new country (leave empty to keep current: {$player['country']}): ");
                            if (!empty($country)) {
                                try {
                                    $crud->validateName($country, "Country");
                                    break;
                                } catch (InvalidArgumentException $e) {
                                    echo "Error: " . $e->getMessage() . "\n";
                                }
                            } else {
                                break;
                            }
                        }
                
                        // Обновление позиции игрока
                        while(true){
                            $flag = readline("Enter new position (leave empty to keep current: {$player['position']} or enter '?' to select position): ");
                            if($flag=="?"){
                                $position = $crud->selectPlayerPosition();
                            }else{
                                $position = "";
                            }
                            break;
                             
                        }
                        
                
                        // Обновление команды
                        while (true) {
                            $team_id = readline("Enter new team ID (leave empty to keep current: {$player['team_id']} or enter '?' to list teams): ");
                            if ($team_id === '?') {
                                echo "\nAvailable teams:\n";
                                foreach ($teams as $team) {
                                    echo "ID: {$team['team_id']} - Name: {$team['name']}\n";
                                }
                                continue;
                            }
                            if (!empty($team_id)) {
                                // Проверка, что ID — число и существует
                                if (!preg_match('/^\d+$/', $team_id)) {
                                    echo "Team ID must be a number.\n";
                                    continue;
                                }
                
                                // Проверка существования команды
                                $valid_team = false;
                                foreach ($teams as $team) {
                                    if ((int)$team_id === (int)$team['team_id']) {
                                        $valid_team = true;
                                        break;
                                    }
                                }
                
                                if ($valid_team) {
                                    break;
                                } else {
                                    echo "Invalid team ID. Try again or type '?' to list teams.\n";
                                    continue;
                                }
                            } else {
                                break;
                            }
                        }
                
                        // Попытка обновить данные игрока
                        try {
                            $crud->update($id, $name, $surname, $birth_date, $country, $position, $team_id);
                            echo "Player updated successfully.\n";
                            break;
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                            $retry = readline("Do you want to try again? (yes/no): ");
                            if (strtolower($retry) !== 'yes') {
                                echo "Update canceled.\n";
                            }
                        }
                    } else {
                        echo "Player not found.\n";
                    }
                    break;
                    
                

            case '5':
                $id = readline("Enter player ID to delete: ");
                if ($crud->retrieve($id)) {
                    $crud->delete($id);
                    echo "Player deleted.\n";
                } else {
                    echo "Player not found.\n";
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
                            $nonExistingIds[] = $id;
                        }
                    }

                    if (empty($existingIds)) {
                        echo "No players found with the given IDs.\n";
                    } else {
                        // Выводим отсутствующие ID, если они есть
                        if (!empty($nonExistingIds)) {
                            echo "The following IDs were not found: " . implode(', ', $nonExistingIds) . "\n";
                        }
                        // Удаляем существующие записи
                        $crud->deleteMany($existingIds);
                        echo "Players with IDs " . implode(', ', $existingIds) . " have been deleted.\n";
                    }
                } else { 
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