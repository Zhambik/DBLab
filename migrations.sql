

CREATE SEQUENCE teams_team_id_seq;

CREATE TABLE teams (
    team_id INT DEFAULT nextval('teams_team_id_seq') PRIMARY KEY,
    name VARCHAR(64) NOT NULL 
        CHECK (name <> '' AND name NOT SIMILAR TO '%[0-9]%' AND name NOT LIKE '% ' AND name NOT LIKE ' %' AND name ~ '^[A-Za-z  -]+$'),
    country VARCHAR(64) NOT NULL 
        CHECK (country <> '' AND country NOT SIMILAR TO '%[0-9]%' AND country NOT LIKE '% ' AND country NOT LIKE ' %' AND country ~ '^[A-Za-z  -]+$'),
    UNIQUE (name, country)  -- Уникальная комбинация имени и страны для команды
);

CREATE UNIQUE INDEX teams_name_country_unique ON teams (LOWER(name), LOWER(country));

  
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
    name VARCHAR(64) NOT NULL 
        CHECK (name <> '' AND name NOT SIMILAR TO '%[0-9]%'  AND name NOT LIKE '% ' AND name NOT LIKE ' %' AND name ~ '^[A-Za-z -]+$'),
    surname VARCHAR(64) NOT NULL 
        CHECK (surname <> '' AND surname NOT SIMILAR TO '%[0-9]%'  AND surname NOT LIKE '% ' AND surname NOT LIKE ' %' AND surname  ~ '^[A-Za-z -]+$'),
    birth_date DATE NOT NULL
        CHECK(birth_date <= CURRENT_DATE - INTERVAL '16 years'),
    country VARCHAR(64) NOT NULL 
        CHECK (country <> '' AND country NOT SIMILAR TO '%[0-9]%'  AND country NOT LIKE '% ' AND country NOT LIKE ' %' AND country ~ '^[A-Za-z -]+$'),
    position VARCHAR(16) NOT NULL 
        CHECK (position IN ('Forward', 'Midfielder', 'Defender', 'Goalkeeper')),
    team_id INT,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX players_unique ON teams (LOWER(name), LOWER(surname),LOWER(country), LOWER(position));


INSERT INTO players (player_id, name, surname, birth_date, country, position, team_id)
VALUES
(1, 'Erling', 'Haaland', '2000-07-21', 'Norway', 'Forward', 1),
(2, 'Lionel', 'Messi', '1987-06-24', 'Argentina', 'Forward', 5),
(3, 'Kai', 'Havertz', '1999-06-11', 'Germany', 'Forward', 2),
(4, 'Mohamed', 'Salah', '1992-06-15', 'Egypt', 'Forward', 4),
(5, 'Kylian', 'Mbappe', '1998-12-20', 'France', 'Forward', 8),
(6, 'Ollie', 'Watkins', '1995-12-30', 'England', 'Forward', 9),
(7, 'Dani', 'Olmo', '1998-05-07', 'Spain', 'Midfielder', 7),
(8, 'Harry', 'Kane', '1993-07-28', 'England', 'Forward', 6),
(9, 'Cole', 'Palmer', '2002-05-06', 'England', 'Midfielder', 3),
(10, 'Khvicha', 'Kvaratskhelia', '2001-02-12', 'Georgia', 'Forward', 10);

ALTER TABLE players
ADD CONSTRAINT unique_players_birth_date UNIQUE (name, surname,country, birth_date);

SELECT setval('players_player_id_seq', (SELECT MAX(player_id)  FROM players));

create SEQUENCE coaches_coach_id_seq;


CREATE TABLE coaches (
    coach_id INT DEFAULT nextval('coaches_coach_id_seq') PRIMARY KEY,
    name VARCHAR(64) NOT NULL 
        CHECK (name <> '' AND name NOT SIMILAR TO '%[0-9]%'  AND name NOT LIKE '% ' AND name NOT LIKE ' %' AND name ~ '^[A-Za-z -]+$'),
    surname VARCHAR(64) NOT NULL 
        CHECK (surname <> '' AND surname NOT SIMILAR TO '%[0-9]%'  AND surname NOT LIKE '% ' AND surname NOT LIKE ' %' AND surname ~ '^[A-Za-z -]+$'),
    birth_date DATE NOT NULL
        CHECK(birth_date <= CURRENT_DATE - INTERVAL '16 years'),
    country VARCHAR(64) NOT NULL 
        CHECK (country <> '' AND country NOT SIMILAR TO '%[0-9]%'  AND country NOT LIKE '% ' AND country NOT LIKE ' %' AND country ~ '^[A-Za-z -]+$')
);

ALTER TABLE coaches
ADD CONSTRAINT unique_birth_date UNIQUE (name, surname,country, birth_date);




INSERT INTO coaches (coach_id, name, surname,birth_date ,country)
VALUES
(1, 'Pep', 'Guardiola', '1971-01-18', 'Spain'),
(2, 'Mikel', 'Arteta', '1982-03-26', 'Spain'),
(3, 'Juanma', 'Lillo', '1965-11-02', 'Spain'),
(4, 'Jurgen', 'Klopp', '1967-06-16', 'Germany'),
(5, 'Gerardo', 'Martino', '1962-11-20', 'Argentina'),
(6, 'Vincent', 'Kompany', '1986-04-10', 'Belgium'),
(7, 'Hansi', 'Flik', '1965-02-24', 'Germany'),
(8, 'Carlo', 'Ancelotti', '1959-06-10', 'Italy'),
(9, 'Unai', 'Emery', '1971-11-03', 'Spain'),
(10, 'Luciano', 'Spalletti', '1959-03-07', 'Italy');

SELECT setval('coaches_coach_id_seq', (SELECT MAX(coach_id)  FROM coaches));

CREATE SEQUENCE team_coaches_id_seq;

CREATE TABLE team_coaches ( 
    team_coaches_id INT DEFAULT nextval('team_coaches_id_seq') PRIMARY KEY,
    team_id INT,
    coach_id INT NOT NULL,
    start_date DATE NOT NULL CHECK (start_date <= CURRENT_DATE),
    end_date DATE CHECK (end_date <= CURRENT_DATE AND end_date >= start_date),
    job_title VARCHAR(64) NOT NULL 
        CHECK (job_title <> '' AND job_title NOT SIMILAR TO '%[0-9]%' 
               AND job_title NOT LIKE '% ' AND job_title NOT LIKE ' %' 
               AND job_title ~ '^[A-Za-z -]+$'),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    FOREIGN KEY (coach_id) REFERENCES coaches(coach_id)
);

-- Ограничение на уникальность тренера по команде и должности в указанный период
ALTER TABLE team_coaches
    ADD CONSTRAINT unique_coach_position_period
    UNIQUE (team_id, coach_id, job_title, start_date, end_date);

-- Добавление ограничения на пересечение периодов работы тренера в разных командах
ALTER TABLE team_coaches
    ADD CONSTRAINT no_overlap_coach_periods
    EXCLUDE USING gist (
        coach_id WITH =,
        job_title WITH =,
        daterange(start_date, COALESCE(end_date, '9999-12-31'::date), '[]') WITH &&
    );

ALTER TABLE team_coaches
ADD CONSTRAINT no_overlapping_periods
EXCLUDE USING gist (
    team_id WITH =,
    coach_id WITH =,
    job_title WITH =,
    daterange(start_date, COALESCE(end_date, '9999-12-31'::date), '[]') WITH &&
);



INSERT INTO team_coaches (team_coaches_id ,team_id, coach_id,start_date, end_date, job_title)
VALUES
(1,7, 7, '2024-08-26',NULL, 'Head Coach'),
(2,8, 8, '2013-11-27', '2015-08-26','Head Coach'),
(3,1, 3, '2023-06-12', '2024-11-25','Head Coach'),
(4,4, 4, '2016-01-07', '2024-08-26','Assistant'),
(5,6, 8, '2021-07-30', '2024-08-26','Head Coach'),
(6,6, 6, '2019-10-26', '2024-08-26','Head Coach'),
(7,5, 2, '2014-10-26', '2024-08-26','Head Coach'),
(8,9, 9, '2024-10-26', '2024-08-26','Head Coach'),
(9,10, 10,'2019-9-26','2024-08-15','Head Coach');



SELECT setval('team_coaches_id_seq', (SELECT MAX(team_coaches_id)  FROM team_coaches));

create SEQUENCE matches_match_id_seq;

CREATE TABLE matches (
    match_id INT DEFAULT nextval('matches_match_id_seq') PRIMARY KEY,
    team_1_id INT NOT NULL,
    team_2_id INT NOT NULL,
    team_1_goals INT DEFAULT 0 CHECK (team_1_goals >= 0),
    team_2_goals INT DEFAULT 0 CHECK (team_2_goals >= 0),
    match_date DATE NOT NULL CHECK (match_date <= CURRENT_DATE),
    tournament VARCHAR(64) NOT NULL 
        CHECK (tournament <> '' AND tournament !~ '^[0-9]+$' AND tournament NOT LIKE '% ' AND tournament NOT LIKE ' %' AND tournament ~ '^[A-Za-z -]+$'),
    FOREIGN KEY (team_1_id) REFERENCES teams(team_id) ,
    FOREIGN KEY (team_2_id) REFERENCES teams(team_id) ,
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


CREATE OR REPLACE FUNCTION check_teams_not_play_same_day()
RETURNS TRIGGER AS $$
BEGIN
    -- Проверяем, что ни одна из команд не играет уже в этот день
    IF EXISTS (
        SELECT 1
        FROM matches m
        WHERE m.match_date = NEW.match_date
        AND (
            (m.team_1_id = NEW.team_1_id AND m.team_2_id = NEW.team_2_id)  -- Проверка на те же команды
            OR
            (m.team_1_id = NEW.team_2_id AND m.team_2_id = NEW.team_1_id)  -- Проверка на смену местами команд
        )
    ) THEN
        RAISE EXCEPTION 'The teams cannot play in the same day.';
    END IF;

    -- Проверяем, что команда 1 не играет дважды в тот же день
    IF EXISTS (
        SELECT 1
        FROM matches m
        WHERE m.match_date = NEW.match_date
        AND (
            m.team_1_id = NEW.team_1_id OR m.team_2_id = NEW.team_1_id  -- Проверка для команды 1
        )
    ) THEN
        RAISE EXCEPTION 'Team 1 cannot play more than one match on the same day.';
    END IF;

    -- Проверяем, что команда 2 не играет дважды в тот же день
    IF EXISTS (
        SELECT 1
        FROM matches m
        WHERE m.match_date = NEW.match_date
        AND (
            m.team_1_id = NEW.team_2_id OR m.team_2_id = NEW.team_2_id  -- Проверка для команды 2
        )
    ) THEN
        RAISE EXCEPTION 'Team 2 cannot play more than one match on the same day.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Создаем триггер, который будет проверять пересечения команд перед вставкой или обновлением
CREATE TRIGGER check_same_day_match
BEFORE INSERT OR UPDATE ON matches
FOR EACH ROW
EXECUTE FUNCTION check_teams_not_play_same_day();


SELECT setval('matches_match_id_seq', (SELECT MAX(match_id)  FROM matches));



