CREATE DATABASE IF NOT EXISTS my_db;
USE my_db;

CREATE TABLE game
(
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_player_1 INT,
    id_player_2 INT,
    board_id INT,
    turn INT,
    status VARCHAR(256)
);

CREATE TABLE player
(
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(10),
    name varchar(256)
);

CREATE TABLE board
(
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    n1 VARCHAR(1),
    n2 VARCHAR(1),
    n3 VARCHAR(1),
    n4 VARCHAR(1),
    n5 VARCHAR(1),
    n6 VARCHAR(1),
    n7 VARCHAR(1),
    n8 VARCHAR(1),
    n9 VARCHAR(1)
);