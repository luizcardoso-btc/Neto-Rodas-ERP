-- Catalogo completo: SUVs, picapes e sedas (fotos de lado sem fundo, 3 cores reais)
INSERT IGNORE INTO car_models (brand, model, body_type, length_mm, width_mm, height_mm, wheelbase_mm, tire) VALUES
('Toyota','Camry','sedan',4885,1840,1445,2825,'235/45 R18'),
('Chevrolet','Trailblazer','suv',4887,1902,1846,2845,'265/60 R18'),
('Chevrolet','Equinox','suv',4652,1843,1661,2725,'225/60 R18'),
('Ford','Bronco Sport','suv',4387,1887,1785,2670,'225/65 R17'),
('Ford','Mustang GT','sedan',4811,1916,1404,2720,'255/40 R19'),
('Honda','ZR-V','suv',4568,1840,1620,2657,'225/55 R18'),
('Mitsubishi','Eclipse Cross','suv',4545,1805,1685,2670,'225/55 R18'),
('Mitsubishi','Pajero Sport','suv',4825,1815,1835,2800,'265/60 R18'),
('Ram','1500','picape',5916,2085,1971,3672,'275/55 R20'),
('Renault','Kardian','suv',4119,1748,1544,2604,'205/60 R16'),
('Volkswagen','Tiguan Allspace','suv',4733,1839,1688,2791,'235/55 R18');

UPDATE car_models SET tire = '225/40 R18' WHERE brand = 'Volkswagen' AND model = 'Jetta';

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/byd-song-plus-branco.webp', '{"on":true,"list":[{"x":0.2037,"y":0.779,"r":0.0831,"sq":1},{"x":0.8075,"y":0.779,"r":0.0831,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Song Plus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-song-plus-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/byd-song-plus-prata.webp', '{"on":true,"list":[{"x":0.2025,"y":0.779,"r":0.0831,"sq":1},{"x":0.8088,"y":0.779,"r":0.0831,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Song Plus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-song-plus-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/byd-song-plus-preto.webp', '{"on":true,"list":[{"x":0.2037,"y":0.779,"r":0.0831,"sq":1},{"x":0.8075,"y":0.779,"r":0.0831,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Song Plus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-song-plus-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/byd-yuan-plus-branco.webp', '{"on":true,"list":[{"x":0.1862,"y":0.775,"r":0.0875,"sq":1},{"x":0.825,"y":0.775,"r":0.0875,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Yuan Plus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-yuan-plus-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/byd-yuan-plus-prata.webp', '{"on":true,"list":[{"x":0.1838,"y":0.7875,"r":0.0825,"sq":1},{"x":0.8288,"y":0.7812,"r":0.085,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Yuan Plus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-yuan-plus-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/byd-yuan-plus-preto.webp', '{"on":true,"list":[{"x":0.1862,"y":0.7812,"r":0.085,"sq":1},{"x":0.825,"y":0.7812,"r":0.085,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Yuan Plus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-yuan-plus-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/byd-seal-branco.webp', '{"on":true,"list":[{"x":0.2037,"y":0.7618,"r":0.0785,"sq":1},{"x":0.8312,"y":0.7618,"r":0.0785,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Seal' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-seal-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/byd-seal-prata.webp', '{"on":true,"list":[{"x":0.2025,"y":0.7618,"r":0.0785,"sq":1},{"x":0.8312,"y":0.7618,"r":0.0785,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Seal' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-seal-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/byd-seal-preto.webp', '{"on":true,"list":[{"x":0.2037,"y":0.7624,"r":0.0789,"sq":1},{"x":0.8312,"y":0.7624,"r":0.0789,"sq":1}]}' FROM car_models WHERE brand = 'BYD' AND model = 'Seal' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-seal-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/toyota-corolla-cross-branco.webp', '{"on":true,"list":[{"x":0.1988,"y":0.7863,"r":0.0814,"sq":1},{"x":0.8125,"y":0.7863,"r":0.0814,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'Corolla Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-corolla-cross-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/toyota-corolla-cross-prata.webp', '{"on":true,"list":[{"x":0.1988,"y":0.7867,"r":0.0818,"sq":1},{"x":0.8125,"y":0.7867,"r":0.0818,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'Corolla Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-corolla-cross-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/toyota-corolla-cross-preto.webp', '{"on":true,"list":[{"x":0.2,"y":0.7871,"r":0.0796,"sq":1},{"x":0.8138,"y":0.7871,"r":0.0821,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'Corolla Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-corolla-cross-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/toyota-sw4-branco.webp', '{"on":true,"list":[{"x":0.2162,"y":0.7833,"r":0.0869,"sq":1},{"x":0.8088,"y":0.7894,"r":0.0844,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'SW4' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-sw4-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/toyota-sw4-prata.webp', '{"on":true,"list":[{"x":0.2175,"y":0.7833,"r":0.0869,"sq":1},{"x":0.8088,"y":0.7773,"r":0.0894,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'SW4' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-sw4-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/toyota-sw4-preto.webp', '{"on":true,"list":[{"x":0.2175,"y":0.7837,"r":0.0872,"sq":1},{"x":0.8088,"y":0.7837,"r":0.0872,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'SW4' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-sw4-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/toyota-camry-branco.webp', '{"on":true,"list":[{"x":0.2075,"y":0.7584,"r":0.0766,"sq":1},{"x":0.8075,"y":0.7584,"r":0.0766,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'Camry' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-camry-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/toyota-camry-prata.webp', '{"on":true,"list":[{"x":0.2075,"y":0.7584,"r":0.0766,"sq":1},{"x":0.8075,"y":0.7584,"r":0.0766,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'Camry' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-camry-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/toyota-camry-preto.webp', '{"on":true,"list":[{"x":0.2088,"y":0.7584,"r":0.0766,"sq":1},{"x":0.81,"y":0.7584,"r":0.0766,"sq":1}]}' FROM car_models WHERE brand = 'Toyota' AND model = 'Camry' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-camry-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-tracker-branco.webp', '{"on":true,"list":[{"x":0.1838,"y":0.8111,"r":0.0825,"sq":1},{"x":0.815,"y":0.8111,"r":0.0825,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Tracker' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-tracker-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-tracker-prata.webp', '{"on":true,"list":[{"x":0.1838,"y":0.8111,"r":0.0825,"sq":1},{"x":0.8138,"y":0.8111,"r":0.0825,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Tracker' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-tracker-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-tracker-preto.webp', '{"on":true,"list":[{"x":0.1838,"y":0.8111,"r":0.0825,"sq":1},{"x":0.8138,"y":0.8111,"r":0.0825,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Tracker' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-tracker-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-trailblazer-branco.webp', '{"on":true,"list":[{"x":0.2162,"y":0.7997,"r":0.0871,"sq":1},{"x":0.8312,"y":0.7997,"r":0.0846,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Trailblazer' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-trailblazer-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-trailblazer-prata.webp', '{"on":true,"list":[{"x":0.2162,"y":0.7994,"r":0.0868,"sq":1},{"x":0.8325,"y":0.7994,"r":0.0868,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Trailblazer' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-trailblazer-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-trailblazer-preto.webp', '{"on":true,"list":[{"x":0.215,"y":0.8051,"r":0.0842,"sq":1},{"x":0.8325,"y":0.7994,"r":0.0868,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Trailblazer' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-trailblazer-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-equinox-branco.webp', '{"on":true,"list":[{"x":0.2025,"y":0.7883,"r":0.0832,"sq":1},{"x":0.8125,"y":0.7883,"r":0.0832,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Equinox' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-equinox-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-equinox-prata.webp', '{"on":true,"list":[{"x":0.2062,"y":0.7821,"r":0.0857,"sq":1},{"x":0.8162,"y":0.7821,"r":0.0857,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Equinox' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-equinox-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-equinox-preto.webp', '{"on":true,"list":[{"x":0.2062,"y":0.7821,"r":0.0857,"sq":1},{"x":0.8162,"y":0.7821,"r":0.0857,"sq":1}]}' FROM car_models WHERE brand = 'Chevrolet' AND model = 'Equinox' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-equinox-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/ford-bronco-sport-branco.webp', '{"on":true,"list":[{"x":0.2,"y":0.807,"r":0.0872,"sq":1},{"x":0.8188,"y":0.8124,"r":0.0847,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Bronco Sport' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-bronco-sport-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/ford-bronco-sport-prata.webp', '{"on":true,"list":[{"x":0.2,"y":0.7965,"r":0.0926,"sq":1},{"x":0.8162,"y":0.8019,"r":0.0901,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Bronco Sport' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-bronco-sport-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/ford-bronco-sport-preto.webp', '{"on":true,"list":[{"x":0.2,"y":0.7965,"r":0.0926,"sq":1},{"x":0.8188,"y":0.8072,"r":0.0876,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Bronco Sport' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-bronco-sport-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/ford-ranger-branco.webp', '{"on":true,"list":[{"x":0.22,"y":0.8,"r":0.0775,"sq":1},{"x":0.8462,"y":0.7938,"r":0.08,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Ranger' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-ranger-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/ford-ranger-prata.webp', '{"on":true,"list":[{"x":0.2188,"y":0.7938,"r":0.08,"sq":1},{"x":0.8462,"y":0.7938,"r":0.08,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Ranger' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-ranger-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/ford-ranger-preto.webp', '{"on":true,"list":[{"x":0.2188,"y":0.7941,"r":0.0804,"sq":1},{"x":0.8462,"y":0.7941,"r":0.0804,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Ranger' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-ranger-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/ford-mustang-gt-branco.webp', '{"on":true,"list":[{"x":0.2275,"y":0.7337,"r":0.0834,"sq":1},{"x":0.8238,"y":0.7337,"r":0.0834,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Mustang GT' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-mustang-gt-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/ford-mustang-gt-prata.webp', '{"on":true,"list":[{"x":0.2275,"y":0.7337,"r":0.0834,"sq":1},{"x":0.8238,"y":0.7337,"r":0.0834,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Mustang GT' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-mustang-gt-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/ford-mustang-gt-preto.webp', '{"on":true,"list":[{"x":0.2275,"y":0.7337,"r":0.0834,"sq":1},{"x":0.8238,"y":0.7337,"r":0.0834,"sq":1}]}' FROM car_models WHERE brand = 'Ford' AND model = 'Mustang GT' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ford-mustang-gt-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/honda-hr-v-branco.webp', '{"on":true,"list":[{"x":0.1875,"y":0.7875,"r":0.0825,"sq":1},{"x":0.8162,"y":0.7875,"r":0.0825,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'HR-V' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-hr-v-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/honda-hr-v-prata.webp', '{"on":true,"list":[{"x":0.1888,"y":0.7875,"r":0.0825,"sq":1},{"x":0.8162,"y":0.7875,"r":0.0825,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'HR-V' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-hr-v-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/honda-hr-v-preto.webp', '{"on":true,"list":[{"x":0.1875,"y":0.7879,"r":0.0829,"sq":1},{"x":0.8162,"y":0.7879,"r":0.0829,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'HR-V' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-hr-v-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/honda-zr-v-branco.webp', '{"on":true,"list":[{"x":0.1962,"y":0.7855,"r":0.0806,"sq":1},{"x":0.7988,"y":0.7855,"r":0.0806,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'ZR-V' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-zr-v-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/honda-zr-v-prata.webp', '{"on":true,"list":[{"x":0.1975,"y":0.7855,"r":0.0806,"sq":1},{"x":0.8,"y":0.7855,"r":0.0806,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'ZR-V' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-zr-v-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/honda-zr-v-preto.webp', '{"on":true,"list":[{"x":0.1988,"y":0.779,"r":0.0831,"sq":1},{"x":0.8012,"y":0.779,"r":0.0831,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'ZR-V' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-zr-v-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/honda-civic-hybrid-branco.webp', '{"on":true,"list":[{"x":0.2062,"y":0.7598,"r":0.0774,"sq":1},{"x":0.8188,"y":0.7598,"r":0.0774,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'Civic' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-civic-hybrid-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/honda-civic-hybrid-prata.webp', '{"on":true,"list":[{"x":0.2062,"y":0.7598,"r":0.0774,"sq":1},{"x":0.82,"y":0.7598,"r":0.0774,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'Civic' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-civic-hybrid-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/honda-civic-hybrid-preto.webp', '{"on":true,"list":[{"x":0.2062,"y":0.7598,"r":0.0774,"sq":1},{"x":0.8188,"y":0.7598,"r":0.0774,"sq":1}]}' FROM car_models WHERE brand = 'Honda' AND model = 'Civic' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-civic-hybrid-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/jeep-compass-branco.webp', '{"on":true,"list":[{"x":0.1888,"y":0.7951,"r":0.0908,"sq":1},{"x":0.8125,"y":0.7951,"r":0.0882,"sq":1}]}' FROM car_models WHERE brand = 'Jeep' AND model = 'Compass' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/jeep-compass-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/jeep-compass-prata.webp', '{"on":true,"list":[{"x":0.1875,"y":0.7951,"r":0.0908,"sq":1},{"x":0.8125,"y":0.8005,"r":0.0857,"sq":1}]}' FROM car_models WHERE brand = 'Jeep' AND model = 'Compass' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/jeep-compass-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/jeep-compass-preto.webp', '{"on":true,"list":[{"x":0.1875,"y":0.7951,"r":0.0908,"sq":1},{"x":0.8125,"y":0.8005,"r":0.0882,"sq":1}]}' FROM car_models WHERE brand = 'Jeep' AND model = 'Compass' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/jeep-compass-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/jeep-commander-branco.webp', '{"on":true,"list":[{"x":0.2175,"y":0.7808,"r":0.0846,"sq":1},{"x":0.8275,"y":0.7871,"r":0.0821,"sq":1}]}' FROM car_models WHERE brand = 'Jeep' AND model = 'Commander' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/jeep-commander-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/jeep-commander-prata.webp', '{"on":true,"list":[{"x":0.2175,"y":0.7808,"r":0.0846,"sq":1},{"x":0.8288,"y":0.7808,"r":0.0821,"sq":1}]}' FROM car_models WHERE brand = 'Jeep' AND model = 'Commander' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/jeep-commander-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/jeep-commander-preto.webp', '{"on":true,"list":[{"x":0.2175,"y":0.7808,"r":0.0846,"sq":1},{"x":0.8275,"y":0.7808,"r":0.0821,"sq":1}]}' FROM car_models WHERE brand = 'Jeep' AND model = 'Commander' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/jeep-commander-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/mitsubishi-eclipse-cross-branco.webp', '{"on":true,"list":[{"x":0.185,"y":0.7901,"r":0.0851,"sq":1},{"x":0.7962,"y":0.7901,"r":0.0851,"sq":1}]}' FROM car_models WHERE brand = 'Mitsubishi' AND model = 'Eclipse Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/mitsubishi-eclipse-cross-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/mitsubishi-eclipse-cross-prata.webp', '{"on":true,"list":[{"x":0.1838,"y":0.7901,"r":0.0851,"sq":1},{"x":0.7962,"y":0.7901,"r":0.0851,"sq":1}]}' FROM car_models WHERE brand = 'Mitsubishi' AND model = 'Eclipse Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/mitsubishi-eclipse-cross-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/mitsubishi-eclipse-cross-preto.webp', '{"on":true,"list":[{"x":0.1838,"y":0.7901,"r":0.0851,"sq":1},{"x":0.7962,"y":0.7901,"r":0.0851,"sq":1}]}' FROM car_models WHERE brand = 'Mitsubishi' AND model = 'Eclipse Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/mitsubishi-eclipse-cross-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/mitsubishi-pajero-sport-branco.webp', '{"on":true,"list":[{"x":0.23,"y":0.7883,"r":0.0832,"sq":1},{"x":0.8212,"y":0.7759,"r":0.0882,"sq":1}]}' FROM car_models WHERE brand = 'Mitsubishi' AND model = 'Pajero Sport' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/mitsubishi-pajero-sport-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/mitsubishi-pajero-sport-prata.webp', '{"on":true,"list":[{"x":0.23,"y":0.7883,"r":0.0832,"sq":1},{"x":0.8212,"y":0.7821,"r":0.0857,"sq":1}]}' FROM car_models WHERE brand = 'Mitsubishi' AND model = 'Pajero Sport' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/mitsubishi-pajero-sport-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/mitsubishi-pajero-sport-preto.webp', '{"on":true,"list":[{"x":0.2312,"y":0.7833,"r":0.0869,"sq":1},{"x":0.8212,"y":0.7833,"r":0.0869,"sq":1}]}' FROM car_models WHERE brand = 'Mitsubishi' AND model = 'Pajero Sport' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/mitsubishi-pajero-sport-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/nissan-kicks-branco.webp', '{"on":true,"list":[{"x":0.1875,"y":0.7986,"r":0.0856,"sq":1},{"x":0.8162,"y":0.7929,"r":0.0881,"sq":1}]}' FROM car_models WHERE brand = 'Nissan' AND model = 'Kicks' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-kicks-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/nissan-kicks-prata.webp', '{"on":true,"list":[{"x":0.1838,"y":0.7986,"r":0.0856,"sq":1},{"x":0.8162,"y":0.7929,"r":0.0881,"sq":1}]}' FROM car_models WHERE brand = 'Nissan' AND model = 'Kicks' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-kicks-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/nissan-kicks-preto.webp', '{"on":true,"list":[{"x":0.1838,"y":0.7986,"r":0.0856,"sq":1},{"x":0.8162,"y":0.7929,"r":0.0881,"sq":1}]}' FROM car_models WHERE brand = 'Nissan' AND model = 'Kicks' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-kicks-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/nissan-frontier-branco.webp', '{"on":true,"list":[{"x":0.22,"y":0.7997,"r":0.0771,"sq":1},{"x":0.8412,"y":0.7997,"r":0.0771,"sq":1}]}' FROM car_models WHERE brand = 'Nissan' AND model = 'Frontier' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-frontier-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/nissan-frontier-prata.webp', '{"on":true,"list":[{"x":0.2212,"y":0.7997,"r":0.0771,"sq":1},{"x":0.8425,"y":0.7997,"r":0.0771,"sq":1}]}' FROM car_models WHERE brand = 'Nissan' AND model = 'Frontier' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-frontier-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/nissan-frontier-preto.webp', '{"on":true,"list":[{"x":0.22,"y":0.7934,"r":0.0771,"sq":1},{"x":0.8412,"y":0.7997,"r":0.0771,"sq":1}]}' FROM car_models WHERE brand = 'Nissan' AND model = 'Frontier' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-frontier-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/ram-rampage-branco.webp', '{"on":true,"list":[{"x":0.2125,"y":0.7855,"r":0.0806,"sq":1},{"x":0.8262,"y":0.7661,"r":0.0831,"sq":1}]}' FROM car_models WHERE brand = 'Ram' AND model = 'Rampage' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ram-rampage-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/ram-rampage-prata.webp', '{"on":true,"list":[{"x":0.2125,"y":0.7859,"r":0.081,"sq":1},{"x":0.83,"y":0.7795,"r":0.081,"sq":1}]}' FROM car_models WHERE brand = 'Ram' AND model = 'Rampage' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ram-rampage-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/ram-rampage-preto.webp', '{"on":true,"list":[{"x":0.2125,"y":0.7795,"r":0.0835,"sq":1},{"x":0.8288,"y":0.7795,"r":0.081,"sq":1}]}' FROM car_models WHERE brand = 'Ram' AND model = 'Rampage' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ram-rampage-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/ram-1500-branco.webp', '{"on":true,"list":[{"x":0.2112,"y":0.7757,"r":0.0805,"sq":1},{"x":0.8412,"y":0.7757,"r":0.0805,"sq":1}]}' FROM car_models WHERE brand = 'Ram' AND model = '1500' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ram-1500-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/ram-1500-prata.webp', '{"on":true,"list":[{"x":0.2088,"y":0.782,"r":0.0776,"sq":1},{"x":0.8412,"y":0.782,"r":0.0776,"sq":1}]}' FROM car_models WHERE brand = 'Ram' AND model = '1500' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ram-1500-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/ram-1500-preto.webp', '{"on":true,"list":[{"x":0.2088,"y":0.782,"r":0.0776,"sq":1},{"x":0.8412,"y":0.782,"r":0.0776,"sq":1}]}' FROM car_models WHERE brand = 'Ram' AND model = '1500' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/ram-1500-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/renault-duster-branco.webp', '{"on":true,"list":[{"x":0.1838,"y":0.8,"r":0.0875,"sq":1},{"x":0.82,"y":0.8056,"r":0.085,"sq":1}]}' FROM car_models WHERE brand = 'Renault' AND model = 'Duster' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-duster-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/renault-duster-prata.webp', '{"on":true,"list":[{"x":0.1838,"y":0.8003,"r":0.0854,"sq":1},{"x":0.82,"y":0.8058,"r":0.0854,"sq":1}]}' FROM car_models WHERE brand = 'Renault' AND model = 'Duster' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-duster-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/renault-duster-preto.webp', '{"on":true,"list":[{"x":0.1838,"y":0.7948,"r":0.0904,"sq":1},{"x":0.8188,"y":0.8058,"r":0.0854,"sq":1}]}' FROM car_models WHERE brand = 'Renault' AND model = 'Duster' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-duster-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/renault-kardian-branco.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8176,"r":0.0819,"sq":1},{"x":0.8075,"y":0.8176,"r":0.0819,"sq":1}]}' FROM car_models WHERE brand = 'Renault' AND model = 'Kardian' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-kardian-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/renault-kardian-prata.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8122,"r":0.0844,"sq":1},{"x":0.8075,"y":0.8176,"r":0.0819,"sq":1}]}' FROM car_models WHERE brand = 'Renault' AND model = 'Kardian' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-kardian-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/renault-kardian-preto.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8174,"r":0.0815,"sq":1},{"x":0.8062,"y":0.8174,"r":0.0815,"sq":1}]}' FROM car_models WHERE brand = 'Renault' AND model = 'Kardian' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-kardian-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-t-cross-branco.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8051,"r":0.0842,"sq":1},{"x":0.8112,"y":0.7994,"r":0.0868,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'T-Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-t-cross-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-t-cross-prata.webp', '{"on":true,"list":[{"x":0.1662,"y":0.7994,"r":0.0868,"sq":1},{"x":0.815,"y":0.8051,"r":0.0842,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'T-Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-t-cross-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-t-cross-preto.webp', '{"on":true,"list":[{"x":0.1662,"y":0.8,"r":0.0875,"sq":1},{"x":0.8138,"y":0.8,"r":0.0875,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'T-Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-t-cross-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-tiguan-allspace-branco.webp', '{"on":true,"list":[{"x":0.215,"y":0.7786,"r":0.0827,"sq":1},{"x":0.8175,"y":0.7786,"r":0.0827,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Tiguan Allspace' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-tiguan-allspace-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-tiguan-allspace-prata.webp', '{"on":true,"list":[{"x":0.215,"y":0.7786,"r":0.0827,"sq":1},{"x":0.8188,"y":0.7851,"r":0.0802,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Tiguan Allspace' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-tiguan-allspace-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-tiguan-allspace-preto.webp', '{"on":true,"list":[{"x":0.2138,"y":0.7795,"r":0.0835,"sq":1},{"x":0.82,"y":0.7795,"r":0.0835,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Tiguan Allspace' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-tiguan-allspace-preto.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-jetta-gli-branco.webp', '{"on":true,"list":[{"x":0.2312,"y":0.7565,"r":0.0821,"sq":1},{"x":0.82,"y":0.7565,"r":0.0796,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Jetta' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-jetta-gli-branco.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-jetta-gli-prata.webp', '{"on":true,"list":[{"x":0.2312,"y":0.7565,"r":0.0821,"sq":1},{"x":0.82,"y":0.7565,"r":0.0796,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Jetta' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-jetta-gli-prata.webp');

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-jetta-gli-preto.webp', '{"on":true,"list":[{"x":0.2312,"y":0.7565,"r":0.0821,"sq":1},{"x":0.8188,"y":0.7565,"r":0.0796,"sq":1}]}' FROM car_models WHERE brand = 'Volkswagen' AND model = 'Jetta' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-jetta-gli-preto.webp');
