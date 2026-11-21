-- Catalogo completo, parte 2: 22 carros que estavam sem foto (3 cores reais cada)
INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/renault-kwid-branco.webp', '{"on":true,"list":[{"x":0.15,"y":0.8134,"r":0.0866,"sq":1},{"x":0.8338,"y":0.8081,"r":0.0891,"sq":1}]}' FROM car_models WHERE model = 'Kwid' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-kwid-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/renault-kwid-prata.webp', '{"on":true,"list":[{"x":0.15,"y":0.8135,"r":0.087,"sq":1},{"x":0.8325,"y":0.8083,"r":0.0895,"sq":1}]}' FROM car_models WHERE model = 'Kwid' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-kwid-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/renault-kwid-preto.webp', '{"on":true,"list":[{"x":0.15,"y":0.8137,"r":0.0874,"sq":1},{"x":0.8312,"y":0.8085,"r":0.0899,"sq":1}]}' FROM car_models WHERE model = 'Kwid' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/renault-kwid-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-nivus-branco.webp', '{"on":true,"list":[{"x":0.19,"y":0.8024,"r":0.0805,"sq":1},{"x":0.8112,"y":0.8024,"r":0.0805,"sq":1}]}' FROM car_models WHERE model = 'Nivus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-nivus-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-nivus-prata.webp', '{"on":true,"list":[{"x":0.1938,"y":0.8186,"r":0.0841,"sq":1},{"x":0.8125,"y":0.8238,"r":0.0816,"sq":1}]}' FROM car_models WHERE model = 'Nivus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-nivus-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-nivus-preto.webp', '{"on":true,"list":[{"x":0.1912,"y":0.7964,"r":0.083,"sq":1},{"x":0.8112,"y":0.7964,"r":0.083,"sq":1}]}' FROM car_models WHERE model = 'Nivus' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-nivus-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/toyota-yaris-branco.webp', '{"on":true,"list":[{"x":0.1538,"y":0.8088,"r":0.0788,"sq":1},{"x":0.805,"y":0.8088,"r":0.0788,"sq":1}]}' FROM car_models WHERE model = 'Yaris Hatch' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-yaris-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/toyota-yaris-prata.webp', '{"on":true,"list":[{"x":0.1562,"y":0.8088,"r":0.0788,"sq":1},{"x":0.8038,"y":0.8088,"r":0.0788,"sq":1}]}' FROM car_models WHERE model = 'Yaris Hatch' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-yaris-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/toyota-yaris-preto.webp', '{"on":true,"list":[{"x":0.155,"y":0.8035,"r":0.082,"sq":1},{"x":0.8062,"y":0.8035,"r":0.082,"sq":1}]}' FROM car_models WHERE model = 'Yaris Hatch' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-yaris-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-saveiro-cross-branco.webp', '{"on":true,"list":[{"x":0.1988,"y":0.8086,"r":0.0759,"sq":1},{"x":0.8338,"y":0.8086,"r":0.0784,"sq":1}]}' FROM car_models WHERE model = 'Saveiro Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-saveiro-cross-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-saveiro-cross-prata.webp', '{"on":true,"list":[{"x":0.1988,"y":0.8086,"r":0.0784,"sq":1},{"x":0.8338,"y":0.8086,"r":0.0784,"sq":1}]}' FROM car_models WHERE model = 'Saveiro Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-saveiro-cross-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-saveiro-cross-preto.webp', '{"on":true,"list":[{"x":0.1988,"y":0.8147,"r":0.0738,"sq":1},{"x":0.8312,"y":0.8088,"r":0.0788,"sq":1}]}' FROM car_models WHERE model = 'Saveiro Cross' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-saveiro-cross-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/toyota-hilux-branco.webp', '{"on":true,"list":[{"x":0.2275,"y":0.7912,"r":0.0774,"sq":1},{"x":0.8312,"y":0.7846,"r":0.0799,"sq":1}]}' FROM car_models WHERE model = 'Hilux' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-hilux-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/toyota-hilux-prata.webp', '{"on":true,"list":[{"x":0.2275,"y":0.7916,"r":0.0778,"sq":1},{"x":0.83,"y":0.7916,"r":0.0778,"sq":1}]}' FROM car_models WHERE model = 'Hilux' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-hilux-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/toyota-hilux-preto.webp', '{"on":true,"list":[{"x":0.2275,"y":0.7919,"r":0.0781,"sq":1},{"x":0.83,"y":0.7919,"r":0.0781,"sq":1}]}' FROM car_models WHERE model = 'Hilux' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-hilux-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/toyota-corolla-branco.webp', '{"on":true,"list":[{"x":0.21,"y":0.7709,"r":0.0771,"sq":1},{"x":0.8138,"y":0.7637,"r":0.0746,"sq":1}]}' FROM car_models WHERE model = 'Corolla' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-corolla-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/toyota-corolla-prata.webp', '{"on":true,"list":[{"x":0.2075,"y":0.7709,"r":0.0771,"sq":1},{"x":0.8138,"y":0.7637,"r":0.0746,"sq":1}]}' FROM car_models WHERE model = 'Corolla' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-corolla-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/toyota-corolla-preto.webp', '{"on":true,"list":[{"x":0.21,"y":0.7786,"r":0.075,"sq":1},{"x":0.815,"y":0.7643,"r":0.0775,"sq":1}]}' FROM car_models WHERE model = 'Corolla' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/toyota-corolla-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/peugeot-208-branco.webp', '{"on":true,"list":[{"x":0.17,"y":0.7989,"r":0.086,"sq":1},{"x":0.8138,"y":0.7989,"r":0.086,"sq":1}]}' FROM car_models WHERE model = '208' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/peugeot-208-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/peugeot-208-prata.webp', '{"on":true,"list":[{"x":0.1688,"y":0.7989,"r":0.086,"sq":1},{"x":0.815,"y":0.7989,"r":0.086,"sq":1}]}' FROM car_models WHERE model = '208' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/peugeot-208-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/peugeot-208-preto.webp', '{"on":true,"list":[{"x":0.1688,"y":0.7994,"r":0.0868,"sq":1},{"x":0.815,"y":0.7994,"r":0.0868,"sq":1}]}' FROM car_models WHERE model = '208' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/peugeot-208-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/hyundai-creta-branco.webp', '{"on":true,"list":[{"x":0.1862,"y":0.7898,"r":0.0847,"sq":1},{"x":0.8162,"y":0.7898,"r":0.0847,"sq":1}]}' FROM car_models WHERE model = 'Creta' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/hyundai-creta-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/hyundai-creta-prata.webp', '{"on":true,"list":[{"x":0.1862,"y":0.7898,"r":0.0847,"sq":1},{"x":0.8162,"y":0.7898,"r":0.0847,"sq":1}]}' FROM car_models WHERE model = 'Creta' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/hyundai-creta-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/hyundai-creta-preto.webp', '{"on":true,"list":[{"x":0.1862,"y":0.7898,"r":0.0847,"sq":1},{"x":0.8162,"y":0.7898,"r":0.0847,"sq":1}]}' FROM car_models WHERE model = 'Creta' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/hyundai-creta-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/honda-fit-branco.webp', '{"on":true,"list":[{"x":0.1638,"y":0.8107,"r":0.0818,"sq":1},{"x":0.8125,"y":0.8107,"r":0.0818,"sq":1}]}' FROM car_models WHERE model = 'Fit' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-fit-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/honda-fit-prata.webp', '{"on":true,"list":[{"x":0.1625,"y":0.8107,"r":0.0818,"sq":1},{"x":0.8138,"y":0.8051,"r":0.0818,"sq":1}]}' FROM car_models WHERE model = 'Fit' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-fit-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/honda-fit-preto.webp', '{"on":true,"list":[{"x":0.1662,"y":0.8161,"r":0.0789,"sq":1},{"x":0.815,"y":0.8161,"r":0.0789,"sq":1}]}' FROM car_models WHERE model = 'Fit' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-fit-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/honda-city-branco.webp', '{"on":true,"list":[{"x":0.2075,"y":0.7866,"r":0.0732,"sq":1},{"x":0.8088,"y":0.7866,"r":0.0732,"sq":1}]}' FROM car_models WHERE model = 'City' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-city-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/honda-city-prata.webp', '{"on":true,"list":[{"x":0.2062,"y":0.7862,"r":0.0729,"sq":1},{"x":0.8088,"y":0.7862,"r":0.0729,"sq":1}]}' FROM car_models WHERE model = 'City' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-city-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/honda-city-preto.webp', '{"on":true,"list":[{"x":0.2075,"y":0.7862,"r":0.0729,"sq":1},{"x":0.8075,"y":0.7862,"r":0.0729,"sq":1}]}' FROM car_models WHERE model = 'City' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/honda-city-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-pulse-branco.webp', '{"on":true,"list":[{"x":0.1612,"y":0.7977,"r":0.0845,"sq":1},{"x":0.8062,"y":0.7977,"r":0.082,"sq":1}]}' FROM car_models WHERE model = 'Pulse' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-pulse-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-pulse-prata.webp', '{"on":true,"list":[{"x":0.1588,"y":0.8245,"r":0.0835,"sq":1},{"x":0.8088,"y":0.8194,"r":0.0835,"sq":1}]}' FROM car_models WHERE model = 'Pulse' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-pulse-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-pulse-preto.webp', '{"on":true,"list":[{"x":0.1575,"y":0.8035,"r":0.082,"sq":1},{"x":0.8062,"y":0.7977,"r":0.082,"sq":1}]}' FROM car_models WHERE model = 'Pulse' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-pulse-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-strada-branco.webp', '{"on":true,"list":[{"x":0.2037,"y":0.8151,"r":0.077,"sq":1},{"x":0.8338,"y":0.8151,"r":0.077,"sq":1}]}' FROM car_models WHERE model = 'Strada' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-strada-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-strada-prata.webp', '{"on":true,"list":[{"x":0.2037,"y":0.8093,"r":0.0795,"sq":1},{"x":0.835,"y":0.8151,"r":0.077,"sq":1}]}' FROM car_models WHERE model = 'Strada' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-strada-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-strada-preto.webp', '{"on":true,"list":[{"x":0.2037,"y":0.8093,"r":0.0795,"sq":1},{"x":0.835,"y":0.8151,"r":0.077,"sq":1}]}' FROM car_models WHERE model = 'Strada' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-strada-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-fastback-branco.webp', '{"on":true,"list":[{"x":0.2125,"y":0.7904,"r":0.0766,"sq":1},{"x":0.8175,"y":0.7838,"r":0.0791,"sq":1}]}' FROM car_models WHERE model = 'Fastback' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-fastback-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-fastback-prata.webp', '{"on":true,"list":[{"x":0.2125,"y":0.7838,"r":0.0791,"sq":1},{"x":0.8175,"y":0.7838,"r":0.0791,"sq":1}]}' FROM car_models WHERE model = 'Fastback' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-fastback-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-fastback-preto.webp', '{"on":true,"list":[{"x":0.2125,"y":0.7908,"r":0.077,"sq":1},{"x":0.8175,"y":0.7842,"r":0.0795,"sq":1}]}' FROM car_models WHERE model = 'Fastback' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-fastback-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-s10-branco.webp', '{"on":true,"list":[{"x":0.2312,"y":0.8009,"r":0.0786,"sq":1},{"x":0.825,"y":0.8009,"r":0.0786,"sq":1}]}' FROM car_models WHERE model = 'S10' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-s10-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-s10-prata.webp', '{"on":true,"list":[{"x":0.2312,"y":0.8009,"r":0.0786,"sq":1},{"x":0.8262,"y":0.8009,"r":0.0786,"sq":1}]}' FROM car_models WHERE model = 'S10' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-s10-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-s10-preto.webp', '{"on":true,"list":[{"x":0.2325,"y":0.8009,"r":0.0786,"sq":1},{"x":0.8262,"y":0.8009,"r":0.0786,"sq":1}]}' FROM car_models WHERE model = 'S10' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-s10-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/chevrolet-montana-branco.webp', '{"on":true,"list":[{"x":0.2062,"y":0.8157,"r":0.0781,"sq":1},{"x":0.8262,"y":0.8157,"r":0.0781,"sq":1}]}' FROM car_models WHERE model = 'Montana' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-montana-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/chevrolet-montana-prata.webp', '{"on":true,"list":[{"x":0.2062,"y":0.8157,"r":0.0781,"sq":1},{"x":0.825,"y":0.8157,"r":0.0781,"sq":1}]}' FROM car_models WHERE model = 'Montana' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-montana-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/chevrolet-montana-preto.webp', '{"on":true,"list":[{"x":0.205,"y":0.8102,"r":0.081,"sq":1},{"x":0.825,"y":0.8102,"r":0.081,"sq":1}]}' FROM car_models WHERE model = 'Montana' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/chevrolet-montana-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/citroen-c3-branco.webp', '{"on":true,"list":[{"x":0.1488,"y":0.8176,"r":0.0819,"sq":1},{"x":0.825,"y":0.8176,"r":0.0819,"sq":1}]}' FROM car_models WHERE model = 'C3' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/citroen-c3-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/citroen-c3-prata.webp', '{"on":true,"list":[{"x":0.1475,"y":0.8176,"r":0.0819,"sq":1},{"x":0.8238,"y":0.8176,"r":0.0819,"sq":1}]}' FROM car_models WHERE model = 'C3' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/citroen-c3-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/citroen-c3-preto.webp', '{"on":true,"list":[{"x":0.1488,"y":0.8177,"r":0.0822,"sq":1},{"x":0.825,"y":0.8177,"r":0.0822,"sq":1}]}' FROM car_models WHERE model = 'C3' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/citroen-c3-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/byd-dolphin-mini-branco.webp', '{"on":true,"list":[{"x":0.155,"y":0.7882,"r":0.0893,"sq":1},{"x":0.8362,"y":0.7938,"r":0.0893,"sq":1}]}' FROM car_models WHERE model = 'Dolphin Mini' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-dolphin-mini-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/byd-dolphin-mini-prata.webp', '{"on":true,"list":[{"x":0.155,"y":0.7941,"r":0.0871,"sq":1},{"x":0.8412,"y":0.7997,"r":0.0871,"sq":1}]}' FROM car_models WHERE model = 'Dolphin Mini' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-dolphin-mini-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/byd-dolphin-mini-preto.webp', '{"on":true,"list":[{"x":0.155,"y":0.7885,"r":0.0896,"sq":1},{"x":0.8412,"y":0.7885,"r":0.0921,"sq":1}]}' FROM car_models WHERE model = 'Dolphin Mini' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-dolphin-mini-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/byd-dolphin-branco.webp', '{"on":true,"list":[{"x":0.1662,"y":0.7883,"r":0.0832,"sq":1},{"x":0.8312,"y":0.7944,"r":0.0807,"sq":1}]}' FROM car_models WHERE model = 'Dolphin' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-dolphin-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/byd-dolphin-prata.webp', '{"on":true,"list":[{"x":0.165,"y":0.7883,"r":0.0832,"sq":1},{"x":0.8275,"y":0.7883,"r":0.0832,"sq":1}]}' FROM car_models WHERE model = 'Dolphin' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-dolphin-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/byd-dolphin-preto.webp', '{"on":true,"list":[{"x":0.1662,"y":0.7883,"r":0.0832,"sq":1},{"x":0.8288,"y":0.7883,"r":0.0832,"sq":1}]}' FROM car_models WHERE model = 'Dolphin' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-dolphin-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/byd-king-branco.webp', '{"on":true,"list":[{"x":0.2138,"y":0.7709,"r":0.0771,"sq":1},{"x":0.8212,"y":0.7709,"r":0.0771,"sq":1}]}' FROM car_models WHERE model = 'King' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-king-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/byd-king-prata.webp', '{"on":true,"list":[{"x":0.2112,"y":0.7781,"r":0.0746,"sq":1},{"x":0.8212,"y":0.7709,"r":0.0771,"sq":1}]}' FROM car_models WHERE model = 'King' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-king-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/byd-king-preto.webp', '{"on":true,"list":[{"x":0.2125,"y":0.772,"r":0.0779,"sq":1},{"x":0.8212,"y":0.772,"r":0.0779,"sq":1}]}' FROM car_models WHERE model = 'King' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/byd-king-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/fiat-toro-branco.webp', '{"on":true,"list":[{"x":0.2,"y":0.8149,"r":0.0766,"sq":1},{"x":0.82,"y":0.8091,"r":0.0791,"sq":1}]}' FROM car_models WHERE model = 'Toro' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-toro-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/fiat-toro-prata.webp', '{"on":true,"list":[{"x":0.2,"y":0.8088,"r":0.0788,"sq":1},{"x":0.82,"y":0.8088,"r":0.0788,"sq":1}]}' FROM car_models WHERE model = 'Toro' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-toro-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/fiat-toro-preto.webp', '{"on":true,"list":[{"x":0.1988,"y":0.8091,"r":0.0791,"sq":1},{"x":0.8175,"y":0.8091,"r":0.0791,"sq":1}]}' FROM car_models WHERE model = 'Toro' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/fiat-toro-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/nissan-versa-branco.webp', '{"on":true,"list":[{"x":0.2037,"y":0.7967,"r":0.0738,"sq":1},{"x":0.82,"y":0.79,"r":0.0738,"sq":1}]}' FROM car_models WHERE model = 'Versa' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-versa-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/nissan-versa-prata.webp', '{"on":true,"list":[{"x":0.2037,"y":0.7967,"r":0.0738,"sq":1},{"x":0.82,"y":0.79,"r":0.0738,"sq":1}]}' FROM car_models WHERE model = 'Versa' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-versa-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/nissan-versa-preto.webp', '{"on":true,"list":[{"x":0.2037,"y":0.7967,"r":0.0738,"sq":1},{"x":0.82,"y":0.79,"r":0.0738,"sq":1}]}' FROM car_models WHERE model = 'Versa' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/nissan-versa-preto.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Branco', 0, 'cars/vw-saveiro-branco.webp', '{"on":true,"list":[{"x":0.2013,"y":0.8199,"r":0.0722,"sq":1},{"x":0.8238,"y":0.8199,"r":0.0697,"sq":1}]}' FROM car_models WHERE model = 'Saveiro' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-saveiro-branco.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Prata', 1, 'cars/vw-saveiro-prata.webp', '{"on":true,"list":[{"x":0.2013,"y":0.8199,"r":0.0722,"sq":1},{"x":0.8262,"y":0.8199,"r":0.0697,"sq":1}]}' FROM car_models WHERE model = 'Saveiro' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-saveiro-prata.webp') LIMIT 1;

INSERT INTO car_images (car_id, angle, color, sort, path, wheels) SELECT id, 'Lateral direita', 'Preto', 2, 'cars/vw-saveiro-preto.webp', '{"on":true,"list":[{"x":0.2,"y":0.8197,"r":0.0719,"sq":1},{"x":0.8262,"y":0.8136,"r":0.0719,"sq":1}]}' FROM car_models WHERE model = 'Saveiro' AND NOT EXISTS (SELECT 1 FROM car_images WHERE path = 'cars/vw-saveiro-preto.webp') LIMIT 1;
