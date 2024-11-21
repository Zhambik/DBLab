

CREATE SEQUENCE teams_team_id_seq;
CREATE TABLE teams (
    team_id INT DEFAULT nextval('teams_team_id_seq')  PRIMARY KEY ,
    name VARCHAR(64) NOT NULL CHECK (name <> '' AND name NOT LIKE '% ' AND name NOT LIKE ' %'),
    country VARCHAR(64) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    UNIQUE (name, country)  -- Уникальная комбинация имени и страны для команды
);
 
INSERT INTO teams (team_id, name, country)
VALUES
(1,'Manchester City', 'England'),
(2,'Arsenal', 'England'),
(3,'Chelsea', 'England'),
(4,'Liverpool', 'England'),
(5,'Inter-Miami', 'USA'),
(6,'Bayern Munich', 'Germany'),
(7,'Barcelona', 'Spain'),
(8,'Real Madrid', 'Spain'),
(9,'Aston Villa', 'England'),
(10,'Napoli', 'Italy');

SELECT setval('teams_team_id_seq', (SELECT MAX(team_id)  FROM teams));


create SEQUENCE players_player_id_seq;
CREATE TABLE players (
    player_id INT DEFAULT nextval('players_player_id_seq') PRIMARY KEY,
    name VARCHAR(64) NOT NULL CHECK (name <> '' AND name NOT LIKE '% ' AND name NOT LIKE ' %'),
    surname VARCHAR(64) NOT NULL CHECK (surname <> '' AND surname NOT LIKE '% ' AND surname NOT LIKE ' %'),
    country VARCHAR(64) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    position VARCHAR(16) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    team_id INT,
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    UNIQUE (name, surname, team_id)  -- Уникальность игрока в одной команде
);

INSERT INTO players (player_id, name, surname, country, position, team_id)
VALUES
(1,'Erling', 'Haaland', 'Norway', 'Forward', 1),
(2,'Lionel', 'Messi', 'Argentina', 'Forward', 5),
(3,'Kai', 'Haverts', 'Germany', 'Forward', 2),
(4,'Mohamed', 'Salah', 'Egypt', 'Forward', 4),
(5,'Kylian', 'Mbappé', 'France', 'Forward', 8),
(6,'Ollie', 'Watkins', 'Portugal', 'Forward', 9),
(7,'Dani', 'Olmo', 'Spain', 'Midfielder', 7),
(8,'Harry', 'Kane', 'England', 'Forward', 6),
(9,'Coul', 'Palmer', 'England', 'Midfielder', 3),
(10,'Khvicha', 'Kvaratskhelia', 'Georgia', 'Forward', 10);
SELECT setval('players_player_id_seq', (SELECT MAX(player_id)  FROM players));

create SEQUENCE coaches_coach_id_seq;
CREATE TABLE coaches (
    coach_id INT DEFAULT nextval('coaches_coach_id_seq') PRIMARY KEY ,
    name VARCHAR(64) NOT NULL CHECK (name <> '' AND name NOT LIKE '% ' AND name NOT LIKE ' %'),
    surname VARCHAR(64) NOT NULL CHECK (surname <> '' AND surname NOT LIKE '% ' AND surname NOT LIKE ' %'),
    country VARCHAR(64) NOT NULL CHECK (country <> '' AND country NOT LIKE '% ' AND country NOT LIKE ' %'),
    UNIQUE (name, surname)  
);

INSERT INTO coaches (coach_id, name, surname, country)
VALUES
(1,'Pep', 'Guardiola', 'Spain'),
(2,'Mikel', 'Arteta', 'Spain'),
(3,'Juanma', 'Lillo', 'Spain'),
(4,'Jürgen', 'Klopp', 'Germany'),
(5,'Gerardo', 'Martino', 'Argentina'),
(6,'Vincent', 'Kompany', 'Belgium'),
(7,'Hansi', 'Flik', 'Germany'),
(8,'Carlo', 'Ancelotti', 'Italy'),
(9,'Unai', 'Emery', 'Spain'),
(10,'Luciano', 'Spalletti', 'Italy');
SELECT setval('coaches_coach_id_seq', (SELECT MAX(coach_id)  FROM coaches));

create SEQUENCE team_coaches_id_seq;
CREATE TABLE team_coaches (
    team_coaches_id INT DEFAULT nextval('team_coaches_id_seq') PRIMARY KEY ,
    team_id INT NOT NULL,
    coach_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    job_title VARCHAR(64) NOT NULL CHECK (job_title <> '' AND job_title NOT LIKE '% ' AND job_title NOT LIKE ' %'),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    FOREIGN KEY (coach_id) REFERENCES coaches(coach_id)
);

INSERT INTO team_coaches (team_coaches_id ,team_id, coach_id,start_date, end_date, job_title)
VALUES
(1,7, 7, '2024-08-26',NULL, 'Head Coach'),
(2,8, 8, '2013-11-27', '2015-08-26','Head Coach'),
(3,1, 3, '2023-06-12', '2024-12-26','Head Coach'),
(4,4, 4, '2016-01-07', '2024-08-26','Assistant'),
(5,6, 8, '2021-07-30', '2024-08-26','Head Coach'),
(6,6, 6, '2019-10-26', '2024-08-26','Head Coach'),
(7,5, 2, '2014-10-26', '2024-08-26','Head Coach'),
(8,9, 9, '2024-10-26', '2024-08-26','Head Coach'),
(9,10, 10,'2019-9-26','2024-08-15','Head Coach'),
(10,11,11,'2023-07-05',NULL,'Head Coach');
SELECT setval('team_coaches_id_seq', (SELECT MAX(team_coaches_id)  FROM team_coaches));
create SEQUENCE matches_match_id_seq;
CREATE TABLE matches (
    match_id INT DEFAULT nextval('matches_match_id_seq') PRIMARY KEY,
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

INSERT INTO matches (match_id, team_1_id, team_2_id, team_1_goals, team_2_goals, match_date, tournament)
VALUES
(1,7, 8, 4, 0, '2024-10-26', 'La Liga'),
(2,3, 2, 1, 1, '2024-11-11', 'Premier League'),
(3,2, 9, 3, 2, '2024-11-12', 'Premier League'),
(4,1, 7, 2, 5, '2024-11-13', 'Champions League'),
(5,5, 10, 1, 1, '2024-11-14', 'Serie A'),
(6,7, 8, 2, 2, '2024-11-15', 'La Liga'),
(7,4, 9, 3, 0, '2024-11-16', 'Premier League'),
(8,6, 1, 1, 1, '2024-11-17', 'Champions League'),
(9,5, 2, 3, 1, '2024-11-18', 'Champions League'),
(10,6, 10, 1, 2, '2024-11-19', 'Serie A');

SELECT setval('matches_match_id_seq', (SELECT MAX(match_id)  FROM matches));

-- SELECT 
--     c.name,
--     c.surname,
--     t.name AS team_name,
--     start_date,
--     end_date
-- FROM 
--     teams AS t
-- JOIN 
--     team_coaches AS tc ON t.team_id = tc.team_id
-- JOIN 
--     coaches AS c ON tc.coach_id = c.coach_id
-- WHERE 
--     c.name='Luis';

