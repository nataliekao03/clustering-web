CREATE DATABASE CLUSTERING;

USE CLUSTERING;

CREATE TABLE credentials (
    name VARCHAR(128) NOT NULL,
    username VARCHAR(128) NOT NULL,
    email VARCHAR(128) NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE scores (
    username VARCHAR(128) NOT NULL,
    modelname VARCHAR(128) NOT NULL,
    scores text NOT NULL,
    kmeans text,
    em text
);