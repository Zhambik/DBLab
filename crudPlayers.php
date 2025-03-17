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

    public function validatePosition($position) {
        $validPositions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
        return in_array($position, $validPositions);
    } 

    public function validateName($value, $fieldName) {
        if (empty($value)) {
            throw new InvalidArgumentException("$fieldName cannot be empty.");
        }
        if (preg_match('/[0-9]/', $value)) {
            throw new InvalidArgumentException("$fieldName cannot contain numbers.");
        }
        if (preg_match('/^\s|\s$/', $value)) {
            throw new InvalidArgumentException("$fieldName cannot start or end with a space.");
        }
        if (!preg_match('/^[A-Za-z -]+$/', $value)) {
            throw new InvalidArgumentException("$fieldName can only contain letters, spaces, and hyphens.");
        }
        return true;
    }


    

    // Создание нового игрока
    public function create($name, $surname, $country, $position, $team_id) {

        $name = trim($name);
        $surname = trim($surname);
        $country = trim($country);
        $position = trim($position);
        $team_id = trim($team_id);

        // Проверка на пустые поля
        if (empty($name) || empty($surname) || empty($country) || empty($position) || empty($team_id)) {
            throw new InvalidArgumentException("All fields must be filled.");
        }  

        // Проверка имени
        $this->validateName($name, "Name"); 

        // Проверка фамилии
        $this->validateName($surname, "Surname");

        // Проверка страны
        $this->validateName($country, "Country");

        // Проверка позиции
        $this->validatePosition($position, "Position");
        if (!$this->validatePosition($position)) {
            throw new InvalidArgumentException("Invalid position. Allowed positions: Goalkeeper, Defender, Midfielder, Forward.");
        }

        // Проверка team_id
        if (!preg_match('/^\d+$/', $team_id)) {
            throw new InvalidArgumentException("Team ID must be a number.");
        }
        if (!$this->teamExists($team_id)) {
            throw new InvalidArgumentException("Team does not exist.");
        }

        // Вставка данных в базу
        $stmt = $this->pdo->prepare("INSERT INTO players (name, surname, country, position, team_id) 
                                     VALUES (:name, :surname, :country, :position, :team_id)");
        $stmt->execute([
            'name' => $name,
            'surname' => $surname,
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
                                    p.country,
                                    p.position,
                                    p.team_id,
                                    t.name AS team_name
                                FROM players p
                                JOIN teams t ON p.team_id = t.team_id;");
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
    public function update($id, $name, $surname, $country, $position, $team_id) {
        // Получение текущих данных записи
        $currentData = $this->retrieve($id);

        // Очистка данных
        $name = trim($name);
        $surname = trim($surname);
        $country = trim($country);
        $position = trim($position);
        $team_id = trim($team_id);

        // Если поле пустое, использовать текущее значение из базы
        $name = !empty($name) ? $name : $currentData['name'];
        $surname = !empty($surname) ? $surname : $currentData['surname'];
        $country = !empty($country) ? $country : $currentData['country'];
        $position = !empty($position) ? $position : $currentData['position'];
        $team_id = !empty($team_id) ? $team_id : $currentData['team_id'];

        // Проверка имени
        $this->validateName($name, "Name");

        // Проверка фамилии
        $this->validateName($surname, "Surname");

        // Проверка страны
        $this->validateName($country, "Country");

        // Проверка на корректность team_id
        if (!preg_match('/^\d+$/', $team_id)) {
            throw new InvalidArgumentException("Team ID must be a number.");
        }

        if (!$this->teamExists($team_id)) {
            throw new InvalidArgumentException("Team does not exist.");
        }

        if (!$this->validatePosition($position)) {
            throw new InvalidArgumentException("Invalid position.");
        }

        // Подготовка и выполнение SQL-запроса
        $stmt = $this->pdo->prepare("UPDATE players 
                                     SET name = :name, surname = :surname, country = :country, 
                                         position = :position, team_id = :team_id 
                                     WHERE player_id = :id");
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'surname' => $surname,
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

    $crud = new PlayerCRUD($dbConfig);

    while (true) {
        echo "\n1. Create Player\n2. Retrieve All Players\n3. Retrieve Player\n4. Update Player\n5. Delete Player\n6. Delete Many Players\n7. Exit\n";

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
                    $name = readline("\nEnter player name: ");
                    $surname = readline("Enter player surname: ");
                    $country = readline("Enter player country: ");
                    $position = readline("Enter player position (Goalkeeper, Defender, Midfielder, Forward): ");
                    $team_id = readline("Enter team ID: ");

                    try {
                        $crud->create($name, $surname, $country, $position, $team_id);
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
                    echo "\nPlayer ID: {$player['player_id']}\nName: {$player['name']}\nSurname: {$player['surname']}\nCountry: {$player['country']}\nPosition: {$player['position']}\nTeam ID: {$player['team_id']}\nTeam Name: {$player['team_name']}\n\n";
                    while(true){
                        $name = readline("Enter new name: ");
                        $surname = readline("Enter new surname: ");
                        $country = readline("Enter new country: ");
                        $position = readline("Enter new position (Goalkeeper, Defender, Midfielder, Forward): ");
                        $team_id = readline("Enter new team ID: ");
                        try {
                            $crud->update($id, $name, $surname, $country, $position, $team_id);
                            echo "Player updated.\n";
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
                            $nonExistingIds[] = $id; // Добавляем в массив отсутствующих ID
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