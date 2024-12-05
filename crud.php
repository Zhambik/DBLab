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

    // Создание нового матча
    public function create($team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament) {
       

        if (empty($team_1_goals) || empty($team_2_id) ||  empty($match_date) || empty($tournament)) {
            throw new InvalidArgumentException("All fields must be filled.");
        }
        if($team_1_id==$team_2_id){
            throw new InvalidArgumentException("Teams can't be the same");
        }


        // Подготовка SQL-запроса для вставки нового матча
        $stmt = $this->pdo->prepare("INSERT INTO matches (team_1_id, team_2_id, team_1_goals, team_2_goals, match_date, tournament) 
                                     VALUES (:team1_id, :team2_id, :team1_goals, :team2_goals, :match_date, :tournament)");
        $stmt->execute([
            'team1_id' => $team_1_id,
            'team2_id' => $team_2_id,
            'team1_goals' => $team_1_goals,
            'team2_goals' => $team_2_goals,
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
        if (empty($team_1_goals) || empty($team_2_id) ||  empty($match_date) || empty($tournament)) {
            throw new InvalidArgumentException("All fields must be filled.");
        }
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
}

function main() {

    // Конфигурация базы данных
    $dbConfig = [
        'host' => 'dpg-ct39mtlumphs73dq6no0-a.oregon-postgres.render.com',
        'port' => '5432',
        'dbname' => 'zhambal_6ik4',
        'user' => 'zhambal',
        'password' => 'pBjUfj3bkgFI0uqbU8C7TtQNgL4AWeEC'
    ];

    $crud = new MatchCRUD($dbConfig);

    while (true) {
        echo "\n1. Create Match\n2. Retrieve All Matches\n3. Retrieve Match\n4. Update Match\n5. Delete Match\n6. Delete Many Matches\n7. Exit\n";

        $choice = readline("Choose an option: ");

        switch ($choice) {
            case '1':
                $team_1_id = readline("Enter team 1 ID: ");
                $team_2_id = readline("Enter team 2 ID: ");
                if($crud->retrieve($team_1_id) and $crud->retrieve($team_2_id)){
                    $team_1_goals = readline("Enter new team 1 goals: ");
                    $team_2_goals = readline("Enter new team 2 goals: ");
                    $match_date = readline("Enter new match date (YYYY-MM-DD): ");
                    
                    $validationResult = $crud->validateMatchDate($match_date);

                    if ($validationResult === true) {
                        $tournament = readline("Enter new tournament name: ");
                        try {
                            $crud->create($team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament);
                            echo "Match created.\n";
                        } catch (InvalidArgumentException $e) {
                            echo "Error: " . $e->getMessage() . "\n";
                        }
                    } else {
                        echo "Error: $validationResult\n ";
                        break;
                    }
                    
                }
                
                
                break;

            case '2':
                $matches = $crud->retrieveAll();
                foreach ($matches as $match) {
                    echo "Match ID: {$match['match_id']}\nTeam 1 ID: {$match['team_1_id']} - Team 2 ID: {$match['team_2_id']}\nTeam 1 name: {$match['team_1_name']} - Team 2 name: {$match['team_2_name']}\nTeam 1 Goals: {$match['team_1_goals']} - Team 2 Goals: {$match['team_2_goals']}\nMatch Date: {$match['match_date']}\nTournament: {$match['tournament']}\n\n";
                }
                break;

            case '3':
                $id = (int)readline("Enter match ID: ");
                if ($match = $crud->retrieve($id)) {
                    echo "Match ID: {$match['match_id']}\nTeam 1 ID: {$match['team_1_id']} - Team 2 ID: {$match['team_2_id']}\nTeam 1 Goals: {$match['team_1_goals']} - Team 2 Goals: {$match['team_2_goals']}\nMatch Date: {$match['match_date']}\nTournament: {$match['tournament']}\n\n";
                } else {
                    echo "Match not found.\n";
                }
                break;

            case '4':
                $id = (int)readline("Enter match ID to update: ");
                if ($crud->retrieve($id)) {
                    $team_1_id = readline("Enter new team 1 ID: ");
                    $team_2_id = readline("Enter new team 2 ID: ");
                    if($crud->retrieve($team_1_id) and $crud->retrieve($team_2_id)){
                        $team_1_goals = readline("Enter new team 1 goals: ");
                        $team_2_goals = readline("Enter new team 2 goals: ");
                        $match_date = readline("Enter new match date (YYYY-MM-DD): ");
                        $Result = $crud->validateMatchDate($match_date);

                        if ($Result === true) {
                            $tournament = readline("Enter new tournament name: ");
                            try {
                                $crud->update($id, $team_1_id, $team_2_id, $team_1_goals, $team_2_goals, $match_date, $tournament);
                                echo "Match updated.\n";
                            } catch (InvalidArgumentException $e) {
                                echo "Error: " . $e->getMessage() . "\n";
                            }
                             
                        }else{
                            echo "Error: $Result";
                            break;
                        }
                    }
                        
                }else {
                    echo "Match not found.\n";
                }
                    
                break;

            case '5':
                $id = (int)readline("Enter match ID to delete: ");
                if ($crud->retrieve($id)) {
                    $crud->delete($id);
                    echo "Match deleted.\n";
                } else {
                    echo "Match not found.\n";
                }
                break;

            case '6':
                $ids = explode(',', readline("Enter match IDs to delete (comma-separated): "));
                $ids = array_map('intval', $ids); // Преобразуем ID в числа

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
                break;

            case '7':
                exit;

            default:
                echo "Invalid choice.\n";
        }
    }
}
main();