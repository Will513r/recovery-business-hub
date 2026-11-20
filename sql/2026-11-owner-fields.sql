-- RBH: private owner details on listings (2026-11-20).
-- Run once in phpMyAdmin on the live RBH database BEFORE the code that uses it deploys.
-- Safe to run early: the current code ignores these columns.
ALTER TABLE businesses
  ADD COLUMN owner_name VARCHAR(100) NOT NULL DEFAULT '' AFTER email,
  ADD COLUMN owner_confirmed_at DATETIME NULL AFTER owner_name;
