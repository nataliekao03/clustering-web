CREATE DATABASE CLUSTERING;

USE CLUSTERING;

CREATE TABLE credentials (
    name VARCHAR(128) NOT NULL,
    username VARCHAR(128) NOT NULL,
    email VARCHAR(128) NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE scores (
    scoresid int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(128) NOT NULL,
    modelname VARCHAR(128) NOT NULL,
    scores text NOT NULL,
    kmid INT,
    emid INT
);

CREATE TABLE km (
    kmid INT AUTO_INCREMENT PRIMARY KEY,
    modelname VARCHAR(128),
    centroid1x FLOAT,
    centroid1y FLOAT,
    centroid2x FLOAT,
    centroid2y FLOAT,
    centroid3x FLOAT,
    centroid3y FLOAT,
    UNIQUE (modelname)
);
--     FOREIGN KEY (kmid) REFERENCES scores (kmid)

CREATE TABLE em (
    emid INT AUTO_INCREMENT PRIMARY KEY,
    modelname VARCHAR(128),
    means VARCHAR(255) NOT NULL,
    variances VARCHAR(255) NOT NULL,
    mixing_coefficents VARCHAR(255) NOT NULL,
    UNIQUE (modelname)
);
--     FOREIGN KEY (emid) REFERENCES scores (emid)

ALTER TABLE scores
ADD FOREIGN KEY (kmid) REFERENCES km (kmid),
ADD FOREIGN KEY (emid) REFERENCES em (emid);

-- to delete all records in scores;
truncate scores;

-- to delete all records in km;
delete from km where kmid > 0;
-- to reset kmid in km to 0
alter table km auto_increment = 0;
