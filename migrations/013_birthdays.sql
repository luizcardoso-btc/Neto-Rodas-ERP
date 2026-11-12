-- Data de nascimento do cliente e controle de parabens enviados
ALTER TABLE customers ADD COLUMN birth_date DATE NULL, ADD COLUMN birthday_sent_year SMALLINT NULL;
