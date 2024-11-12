
CREATE TABLE teams (
    team_id SERIAL PRIMARY KEY ,
    name VARCHAR(64) NOT NULL CHECK (name <> '' AND name NOT LIKE '% ' AND name NOT LIKE ' %'),
    country VARCHAR(64) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    UNIQUE (name, country)  -- Уникальная комбинация имени и страны для команды
);

INSERT INTO teams (name, country)
VALUES
('Manchester City', 'England'),
('Arsenal', 'England'),
('Chelsea', 'England'),
('Liverpool', 'England'),
('Inter-Miami', 'USA'),
('Bayern Munich', 'Germany'),
('Barcelona', 'Spain'),
('Real Madrid', 'Spain'),
('Aston Villa', 'England'),
('Napoli', 'Italy');


CREATE TABLE players (
    player_id SERIAL PRIMARY KEY,
    name VARCHAR(64) NOT NULL CHECK (name <> '' AND name NOT LIKE '% ' AND name NOT LIKE ' %'),
    surname VARCHAR(64) NOT NULL CHECK (surname <> '' AND surname NOT LIKE '% ' AND surname NOT LIKE ' %'),
    country VARCHAR(64) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    position VARCHAR(16) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    team_id INT,
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    UNIQUE (name, surname, team_id)  -- Уникальность игрока в одной команде
);

INSERT INTO players (name, surname, country, position, team_id)
VALUES
('Erling', 'Haaland', 'Norway', 'Forward', 1),
('Lionel', 'Messi', 'Argentina', 'Forward', 5),
('Kai', 'Haverts', 'Germany', 'Forward', 2),
('Mohamed', 'Salah', 'Egypt', 'Forward', 4),
('Kylian', 'Mbappé', 'France', 'Forward', 8),
('Ollie', 'Watkins', 'Portugal', 'Forward', 9),
('Dani', 'Olmo', 'Spain', 'Midfielder', 7),
('Harry', 'Kane', 'England', 'Forward', 6),
('Coul', 'Palmer', 'England', 'Midfielder', 3),
('Khvicha', 'Kvaratskhelia', 'Georgia', 'Forward', 10);

CREATE TABLE coaches (
    coach_id SERIAL PRIMARY KEY ,
    name VARCHAR(64) NOT NULL CHECK (name <> '' AND name NOT LIKE '% ' AND name NOT LIKE ' %'),
    surname VARCHAR(64) NOT NULL CHECK (surname <> '' AND surname NOT LIKE '% ' AND surname NOT LIKE ' %'),
    country VARCHAR(64) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    job_title VARCHAR(64) NOT NULL CHECK (job_title <> '' AND job_title NOT LIKE '% ' AND job_title NOT LIKE ' %'),
    UNIQUE (name, surname)  
);

INSERT INTO coaches (name, surname, country, job_title)
VALUES
('Pep', 'Guardiola', 'Spain', 'Head Coach'),
('Mikel', 'Arteta', 'Spain', 'Head Coach'),
('Juanma', 'Lillo', 'Spain', 'Assistant Coach'),
('Jürgen', 'Klopp', 'Germany', 'Head Coach'),
('Gerardo', 'Martino', 'Argentina', 'Head Coach'),
('Vincent', 'Kompany', 'Belgium', 'Head Coach'),
('Hansi', 'Flik', 'Germany', 'Head Coach'),
('Carlo', 'Ancelotti', 'Italy', 'Head Coach'),
('Unai', 'Emery', 'Spain', 'Head Coach'),
('Luciano', 'Spalletti', 'Italy', 'Head Coach');

CREATE TABLE team_coaches (
    team_id INT,
    coach_id INT,
    PRIMARY KEY (team_id, coach_id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    FOREIGN KEY (coach_id) REFERENCES coaches(coach_id)
);

INSERT INTO team_coaches (team_id, coach_id)
VALUES
(1, 1),
(2, 2),
(1, 3),
(4, 4),
(5, 5),
(6, 6),
(7, 7),
(8, 8),
(9, 9),
(10, 10);


CREATE TABLE matches (
    match_id SERIAL PRIMARY KEY ,
    team_1_id INT NOT NULL,
    team_2_id INT NOT NULL,
    team_1_goals INT DEFAULT 0 CHECK (team_1_goals >= 0),
    team_2_goals INT DEFAULT 0 CHECK (team_2_goals >= 0),
    match_date DATE NOT NULL,
    tournament VARCHAR(64) NOT NULL CHECK (tournament <> '' AND tournament NOT LIKE '% ' AND tournament NOT LIKE ' %'),
    FOREIGN KEY (team_1_id) REFERENCES teams(team_id),
    FOREIGN KEY (team_2_id) REFERENCES teams(team_id),
    CHECK (team_1_id <> team_2_id)  -- Команда не может играть сама с собой
);

INSERT INTO matches (team_1_id, team_2_id, team_1_goals, team_2_goals, match_date, tournament)
VALUES
(7, 8, 4, 0, '2024-10-26', 'La Liga'),
(3, 2, 1, 1, '2024-11-11', 'Premier League'),
(2, 9, 3, 2, '2024-11-12', 'Premier League'),
(1, 7, 2, 5, '2024-11-13', 'Champions League'),
(5, 10, 1, 1, '2024-11-14', 'Serie A'),
(7, 8, 2, 2, '2024-11-15', 'La Liga'),
(4, 9, 3, 0, '2024-11-16', 'Premier League'),
(6, 1, 1, 1, '2024-11-17', 'Champions League'),
(5, 2, 3, 1, '2024-11-18', 'Champions League'),
(6, 10, 1, 2, '2024-11-19', 'Serie A');

