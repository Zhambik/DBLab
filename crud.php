<?php
class TeamCRUD {

    private $pdo;

    public function __construct($dbConfig) {
        try {
            $this->pdo = new PDO("pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}", $dbConfig['user'], $dbConfig['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Could not connect to the database: " . $e->getMessage());
        }
    }

    public function create($name, $country) {
        $stmt = $this->pdo->prepare("INSERT INTO teams (name, country) VALUES (:name, :country)");
        $stmt->execute(['name' => $name, 'country' => $country]);
    }

    public function retrieveAll() {
        $stmt = $this->pdo->query("SELECT * FROM teams");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function retrieve($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM teams WHERE team_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name, $country) {
        $stmt = $this->pdo->prepare("UPDATE teams SET name = :name, country = :country WHERE team_id = :id");
        $stmt->execute(['id' => $id, 'name' => $name, 'country' => $country]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM teams WHERE team_id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function deleteMany($ids) {
        if (empty($ids)) return;

        // Создаем строку с параметрами для запроса
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM teams WHERE team_id IN ($placeholders)");
        $stmt->execute($ids);
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


    // Создаем экземпляр класса
    $crud = new TeamCRUD($dbConfig);

    while (true) {
        echo "\n1. Create\n2. Retrieve All\n3. Retrieve\n4. Update\n5. Delete\n6. Delete Many\n7. Exit\n";

        $choice = readline("Choose an option: ");

        switch ($choice) {
            case '1':
                $name = readline("Enter team name: ");
                $country = readline("Enter team country: ");
                $crud->create($name, $country);
                echo "Team created.\n";
                break;

            case '2':
                $teams = $crud->retrieveAll();
                foreach ($teams as $team) {
                    echo "Team ID: {$team['team_id']}, Team Name: {$team['name']}, Country: {$team['country']}\n";
                }
                break;

            case '3':
                $id = readline("Enter team ID: ");
                $team = $crud->retrieve($id);
                echo "Team ID: {$team['team_id']}, Team Name: {$team['name']}, Country: {$team['country']}\n";
                break;

            case '4':
                $id = readline("Enter team ID to update: ");
                $name = readline("Enter new team name: ");
                $country = readline("Enter new team country: ");
                $crud->update($id, $name, $country);
                echo "Team updated.\n";
                break;

            case '5':
                $id = readline("Enter team ID to delete: ");
                $crud->delete($id);
                echo "Team deleted.\n";
                break;

            case '6':
                $ids = explode(',', readline("Enter team IDs to delete (comma-separated): "));
                $crud->deleteMany($ids);
                echo "Teams deleted.\n";
                break;

            case '7':
                exit;

            default:
                echo "Invalid choice.\n";
        }
    }

}
main();