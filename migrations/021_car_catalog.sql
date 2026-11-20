-- Catalogo de carros do provador (fotos de lado, sem fundo, em 3 cores reais cada)
ALTER TABLE car_images ADD COLUMN color VARCHAR(30) NULL AFTER angle;

INSERT IGNORE INTO car_models (brand, model, body_type, length_mm, width_mm, height_mm, wheelbase_mm, tire) VALUES
('Fiat','Uno','hatch',3815,1636,1555,2376,'175/65 R14'),
('Fiat','Palio','hatch',3875,1670,1504,2420,'175/65 R14'),
('Fiat','Punto','hatch',4065,1687,1490,2510,'185/65 R15'),
('Fiat','Siena','sedan',4200,1615,1440,2373,'175/65 R14'),
('Fiat','Grand Siena','sedan',4291,1700,1512,2510,'175/65 R14'),
('Volkswagen','Fox','hatch',3824,1640,1544,2465,'195/55 R15'),
('Volkswagen','up!','hatch',3605,1645,1504,2421,'175/65 R14'),
('Volkswagen','Voyage','sedan',4218,1656,1464,2467,'175/70 R14'),
('Chevrolet','Celta','hatch',3788,1645,1430,2443,'165/70 R13'),
('Chevrolet','Corsa','hatch',3826,1646,1432,2491,'175/70 R14'),
('Chevrolet','Classic','sedan',4155,1608,1405,2443,'175/70 R13'),
('Chevrolet','Prisma','sedan',4275,1705,1484,2528,'185/70 R14'),
('Chevrolet','Joy','hatch',3933,1705,1474,2528,'185/70 R14'),
('Chevrolet','Agile','hatch',3999,1689,1567,2542,'185/65 R15');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-mobi-branco.webp', '{"on":true,"list":[{"x":0.1525,"y":0.8206,"r":0.089,"sq":1},{"x":0.815,"y":0.8255,"r":0.0865,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Mobi';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-mobi-prata.webp', '{"on":true,"list":[{"x":0.155,"y":0.8204,"r":0.0886,"sq":1},{"x":0.8338,"y":0.8204,"r":0.0886,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Mobi';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-mobi-preto.webp', '{"on":true,"list":[{"x":0.1512,"y":0.8216,"r":0.0916,"sq":1},{"x":0.825,"y":0.8168,"r":0.0866,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Mobi';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-argo-branco.webp', '{"on":true,"list":[{"x":0.1588,"y":0.8172,"r":0.0811,"sq":1},{"x":0.8175,"y":0.8117,"r":0.0811,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Argo';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-argo-prata.webp', '{"on":true,"list":[{"x":0.17,"y":0.8169,"r":0.0804,"sq":1},{"x":0.8238,"y":0.8058,"r":0.0804,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Argo';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-argo-preto.webp', '{"on":true,"list":[{"x":0.155,"y":0.8174,"r":0.0815,"sq":1},{"x":0.8262,"y":0.812,"r":0.079,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Argo';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-uno-branco.webp', '{"on":true,"list":[{"x":0.1625,"y":0.8231,"r":0.0798,"sq":1},{"x":0.8212,"y":0.8177,"r":0.0798,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Uno';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-uno-prata.webp', '{"on":true,"list":[{"x":0.165,"y":0.8195,"r":0.0864,"sq":1},{"x":0.8262,"y":0.8145,"r":0.0889,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Uno';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-uno-preto.webp', '{"on":true,"list":[{"x":0.1612,"y":0.8203,"r":0.0882,"sq":1},{"x":0.815,"y":0.8203,"r":0.0882,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Uno';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-palio-branco.webp', '{"on":true,"list":[{"x":0.165,"y":0.812,"r":0.084,"sq":1},{"x":0.815,"y":0.8174,"r":0.0815,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Palio';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-palio-prata.webp', '{"on":true,"list":[{"x":0.1625,"y":0.824,"r":0.082,"sq":1},{"x":0.8262,"y":0.8188,"r":0.082,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Palio';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-palio-preto.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8181,"r":0.083,"sq":1},{"x":0.8312,"y":0.8181,"r":0.0805,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Palio';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-punto-branco.webp', '{"on":true,"list":[{"x":0.1575,"y":0.81,"r":0.0806,"sq":1},{"x":0.8125,"y":0.7986,"r":0.0806,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Punto';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-punto-prata.webp', '{"on":true,"list":[{"x":0.1588,"y":0.8074,"r":0.088,"sq":1},{"x":0.82,"y":0.8021,"r":0.0855,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Punto';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-punto-preto.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8113,"r":0.0829,"sq":1},{"x":0.8162,"y":0.8058,"r":0.0804,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Punto';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-siena-branco.webp', '{"on":true,"list":[{"x":0.2162,"y":0.8141,"r":0.0751,"sq":1},{"x":0.825,"y":0.8081,"r":0.0776,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Siena';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-siena-prata.webp', '{"on":true,"list":[{"x":0.2188,"y":0.8153,"r":0.0774,"sq":1},{"x":0.835,"y":0.8095,"r":0.0774,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Siena';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-siena-preto.webp', '{"on":true,"list":[{"x":0.2262,"y":0.8218,"r":0.0764,"sq":1},{"x":0.8312,"y":0.8105,"r":0.0789,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Siena';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-grand-siena-branco.webp', '{"on":true,"list":[{"x":0.2088,"y":0.8086,"r":0.0784,"sq":1},{"x":0.825,"y":0.8027,"r":0.0784,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Grand Siena';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-grand-siena-prata.webp', '{"on":true,"list":[{"x":0.2112,"y":0.8081,"r":0.0776,"sq":1},{"x":0.8212,"y":0.8081,"r":0.0751,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Grand Siena';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-grand-siena-preto.webp', '{"on":true,"list":[{"x":0.2175,"y":0.8088,"r":0.0788,"sq":1},{"x":0.8362,"y":0.8029,"r":0.0788,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Grand Siena';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-cronos-branco.webp', '{"on":true,"list":[{"x":0.215,"y":0.8132,"r":0.0736,"sq":1},{"x":0.8138,"y":0.8071,"r":0.0736,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Cronos';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-cronos-prata.webp', '{"on":true,"list":[{"x":0.2225,"y":0.8139,"r":0.0748,"sq":1},{"x":0.8262,"y":0.8139,"r":0.0748,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Cronos';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-cronos-preto.webp', '{"on":true,"list":[{"x":0.215,"y":0.8151,"r":0.077,"sq":1},{"x":0.8262,"y":0.8209,"r":0.0745,"sq":1}]}' FROM car_models WHERE brand = 'Fiat' AND model = 'Cronos';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-polo-branco.webp', '{"on":true,"list":[{"x":0.1625,"y":0.7992,"r":0.0839,"sq":1},{"x":0.8225,"y":0.8048,"r":0.0839,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Polo';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-polo-prata.webp', '{"on":true,"list":[{"x":0.1675,"y":0.8045,"r":0.0835,"sq":1},{"x":0.8325,"y":0.8045,"r":0.081,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Polo';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-polo-preto.webp', '{"on":true,"list":[{"x":0.175,"y":0.7971,"r":0.0838,"sq":1},{"x":0.8238,"y":0.7971,"r":0.0812,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Polo';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-gol-branco.webp', '{"on":true,"list":[{"x":0.1575,"y":0.8058,"r":0.0854,"sq":1},{"x":0.8212,"y":0.8003,"r":0.0829,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Gol';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-gol-prata.webp', '{"on":true,"list":[{"x":0.1562,"y":0.81,"r":0.0806,"sq":1},{"x":0.8112,"y":0.81,"r":0.0781,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Gol';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-gol-preto.webp', '{"on":true,"list":[{"x":0.16,"y":0.8053,"r":0.0846,"sq":1},{"x":0.8138,"y":0.8053,"r":0.0846,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Gol';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-fox-branco.webp', '{"on":true,"list":[{"x":0.1375,"y":0.825,"r":0.085,"sq":1},{"x":0.815,"y":0.82,"r":0.085,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Fox';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-fox-prata.webp', '{"on":true,"list":[{"x":0.14,"y":0.8194,"r":0.086,"sq":1},{"x":0.8175,"y":0.8194,"r":0.086,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Fox';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-fox-preto.webp', '{"on":true,"list":[{"x":0.1438,"y":0.8244,"r":0.0831,"sq":1},{"x":0.8225,"y":0.809,"r":0.0856,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Fox';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-up-branco.webp', '{"on":true,"list":[{"x":0.155,"y":0.8195,"r":0.0864,"sq":1},{"x":0.84,"y":0.8145,"r":0.0864,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'up!';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-up-prata.webp', '{"on":true,"list":[{"x":0.1512,"y":0.8155,"r":0.0911,"sq":1},{"x":0.8438,"y":0.8106,"r":0.0886,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'up!';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-up-preto.webp', '{"on":true,"list":[{"x":0.1525,"y":0.8143,"r":0.0885,"sq":1},{"x":0.8475,"y":0.8194,"r":0.0835,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'up!';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-voyage-branco.webp', '{"on":true,"list":[{"x":0.2138,"y":0.8073,"r":0.0765,"sq":1},{"x":0.8238,"y":0.8073,"r":0.0765,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Voyage';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-voyage-prata.webp', '{"on":true,"list":[{"x":0.215,"y":0.8071,"r":0.0761,"sq":1},{"x":0.82,"y":0.8009,"r":0.0761,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Voyage';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-voyage-preto.webp', '{"on":true,"list":[{"x":0.215,"y":0.8071,"r":0.0761,"sq":1},{"x":0.8212,"y":0.7948,"r":0.0761,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Voyage';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-virtus-branco.webp', '{"on":true,"list":[{"x":0.2212,"y":0.781,"r":0.0769,"sq":1},{"x":0.835,"y":0.7741,"r":0.0769,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Virtus';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-virtus-prata.webp', '{"on":true,"list":[{"x":0.22,"y":0.7923,"r":0.0785,"sq":1},{"x":0.8312,"y":0.7987,"r":0.0735,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Virtus';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-virtus-preto.webp', '{"on":true,"list":[{"x":0.225,"y":0.7977,"r":0.0749,"sq":1},{"x":0.8288,"y":0.7977,"r":0.0749,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Virtus';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/hyundai-hb20-branco.webp', '{"on":true,"list":[{"x":0.1575,"y":0.8056,"r":0.085,"sq":1},{"x":0.8188,"y":0.8111,"r":0.08,"sq":1}]}' FROM car_models WHERE brand = 'Hyundai' AND model = 'HB20';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/hyundai-hb20-prata.webp', '{"on":true,"list":[{"x":0.1675,"y":0.8107,"r":0.0818,"sq":1},{"x":0.83,"y":0.8107,"r":0.0792,"sq":1}]}' FROM car_models WHERE brand = 'Hyundai' AND model = 'HB20';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/hyundai-hb20-preto.webp', '{"on":true,"list":[{"x":0.1512,"y":0.8167,"r":0.08,"sq":1},{"x":0.84,"y":0.8111,"r":0.08,"sq":1}]}' FROM car_models WHERE brand = 'Hyundai' AND model = 'HB20';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/hyundai-hb20s-branco.webp', '{"on":true,"list":[{"x":0.2125,"y":0.8057,"r":0.0742,"sq":1},{"x":0.8275,"y":0.8057,"r":0.0742,"sq":1}]}' FROM car_models WHERE brand = 'Hyundai' AND model = 'HB20S';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/hyundai-hb20s-prata.webp', '{"on":true,"list":[{"x":0.21,"y":0.7944,"r":0.0807,"sq":1},{"x":0.8225,"y":0.7883,"r":0.0782,"sq":1}]}' FROM car_models WHERE brand = 'Hyundai' AND model = 'HB20S';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/hyundai-hb20s-preto.webp', '{"on":true,"list":[{"x":0.2112,"y":0.7997,"r":0.0771,"sq":1},{"x":0.8288,"y":0.7997,"r":0.0771,"sq":1}]}' FROM car_models WHERE brand = 'Hyundai' AND model = 'HB20S';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-onix-branco.webp', '{"on":true,"list":[{"x":0.1775,"y":0.817,"r":0.0807,"sq":1},{"x":0.8212,"y":0.817,"r":0.0782,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Onix';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-onix-prata.webp', '{"on":true,"list":[{"x":0.1775,"y":0.8115,"r":0.0832,"sq":1},{"x":0.8188,"y":0.806,"r":0.0807,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Onix';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-onix-preto.webp', '{"on":true,"list":[{"x":0.1788,"y":0.8051,"r":0.0842,"sq":1},{"x":0.8162,"y":0.8163,"r":0.0792,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Onix';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-onix-plus-branco.webp', '{"on":true,"list":[{"x":0.2075,"y":0.8076,"r":0.0769,"sq":1},{"x":0.8212,"y":0.8015,"r":0.0769,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Onix Plus';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-onix-plus-prata.webp', '{"on":true,"list":[{"x":0.215,"y":0.8042,"r":0.0724,"sq":1},{"x":0.82,"y":0.7912,"r":0.0749,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Onix Plus';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-onix-plus-preto.webp', '{"on":true,"list":[{"x":0.2162,"y":0.8123,"r":0.0721,"sq":1},{"x":0.8212,"y":0.806,"r":0.0721,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Onix Plus';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-celta-branco.webp', '{"on":true,"list":[{"x":0.155,"y":0.8065,"r":0.0865,"sq":1},{"x":0.83,"y":0.8011,"r":0.0865,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Celta';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-celta-prata.webp', '{"on":true,"list":[{"x":0.1562,"y":0.8256,"r":0.0869,"sq":1},{"x":0.83,"y":0.8256,"r":0.0869,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Celta';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-celta-preto.webp', '{"on":true,"list":[{"x":0.1488,"y":0.8068,"r":0.0869,"sq":1},{"x":0.8162,"y":0.8068,"r":0.0844,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Celta';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-corsa-branco.webp', '{"on":true,"list":[{"x":0.145,"y":0.8134,"r":0.0866,"sq":1},{"x":0.8275,"y":0.8186,"r":0.0816,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Corsa';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-corsa-prata.webp', '{"on":true,"list":[{"x":0.1362,"y":0.8135,"r":0.087,"sq":1},{"x":0.835,"y":0.8135,"r":0.082,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Corsa';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-corsa-preto.webp', '{"on":true,"list":[{"x":0.1412,"y":0.8189,"r":0.0849,"sq":1},{"x":0.8238,"y":0.8189,"r":0.0824,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Corsa';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-classic-branco.webp', '{"on":true,"list":[{"x":0.205,"y":0.7977,"r":0.0724,"sq":1},{"x":0.8338,"y":0.7977,"r":0.0749,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Classic';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-classic-prata.webp', '{"on":true,"list":[{"x":0.21,"y":0.7912,"r":0.0774,"sq":1},{"x":0.8388,"y":0.7846,"r":0.0774,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Classic';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-classic-preto.webp', '{"on":true,"list":[{"x":0.21,"y":0.8083,"r":0.078,"sq":1},{"x":0.8375,"y":0.8024,"r":0.0755,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Classic';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-prisma-branco.webp', '{"on":true,"list":[{"x":0.2013,"y":0.8098,"r":0.0802,"sq":1},{"x":0.8288,"y":0.804,"r":0.0802,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Prisma';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-prisma-prata.webp', '{"on":true,"list":[{"x":0.2013,"y":0.8147,"r":0.0762,"sq":1},{"x":0.8312,"y":0.8088,"r":0.0762,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Prisma';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-prisma-preto.webp', '{"on":true,"list":[{"x":0.21,"y":0.8088,"r":0.0788,"sq":1},{"x":0.8362,"y":0.8088,"r":0.0762,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Prisma';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-joy-branco.webp', '{"on":true,"list":[{"x":0.1462,"y":0.806,"r":0.0857,"sq":1},{"x":0.815,"y":0.806,"r":0.0857,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Joy';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-joy-prata.webp', '{"on":true,"list":[{"x":0.1512,"y":0.8074,"r":0.088,"sq":1},{"x":0.8225,"y":0.8074,"r":0.0855,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Joy';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-joy-preto.webp', '{"on":true,"list":[{"x":0.1462,"y":0.8081,"r":0.0891,"sq":1},{"x":0.8225,"y":0.8081,"r":0.0866,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Joy';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-agile-branco.webp', '{"on":true,"list":[{"x":0.1488,"y":0.8137,"r":0.0874,"sq":1},{"x":0.8312,"y":0.8085,"r":0.0824,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Agile';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-agile-prata.webp', '{"on":true,"list":[{"x":0.1538,"y":0.807,"r":0.0872,"sq":1},{"x":0.8325,"y":0.807,"r":0.0847,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Agile';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-agile-preto.webp', '{"on":true,"list":[{"x":0.155,"y":0.8128,"r":0.0855,"sq":1},{"x":0.83,"y":0.8074,"r":0.083,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Agile';
