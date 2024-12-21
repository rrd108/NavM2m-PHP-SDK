# NAV-M2M PHP SDK

Ez a PHP SDK (Software Development Kit) a Nemzeti Adó- és Vámhivatal (NAV) által biztosított M2M (Machine-to-Machine) interfészhez való kapcsolódást segíti. Az SDK lehetővé teszi a gépi kommunikációt a NAV rendszereivel, például bizonylatok beküldéséhez.

Ha a csomag hasznos volt a számodra, akkor nyomj rá a star-ra!

## Telepítés

1.  **Composer használata:** A legkényelmesebb mód az SDK telepítésére a Composer használata:

    ```bash
    composer require rrd108/nav-m2m
    ```

2.  **Követelmények:**
    - PHP 8.0 vagy újabb
    - Composer

## Használat

A `minta.php` fájlban található példák segítségével megismerheted az SDK használatát.

### Inicializálás

Először létre kell hozni egy `NavM2m` objektumot a megfelelő beállításokkal:

```php
<?php

require 'vendor/autoload.php';

use Rrd108\NavM2m\NavM2m;

$client = [
    'id' => $_ENV['NAV2M2M_CLIENT_ID'], // a kliens program azonosítója az UPO-nál
    'secret' => $_ENV['NAV2M2M_CLIENT_SECRET'], // a kliens program titkos kulcsa az UPO-nál
];

$navM2m = new NavM2m(mode: 'sandbox', client: $client, logger: true); // tesztkörnyezet
// vagy
$navM2m = new NavM2m(mode: 'production', client: $client); // éles környezet
```

Ahol:

- `mode`: `"sandbox"` a tesztkörnyezethez, `"production"` az éles környezethez.
- `client`: Egy tömb a `id` (kliens azonosító) és a `secret` (kliens titkos kulcs) adatokkal. Ezeket az Ügyfélkapun kell regisztrálnod a kliensprogramodhoz.

### Felhasználó aktiválása

Minden felhasználót aktiválni kell először annak érdekében, hogy a kliensprogramunk hozzáférhessen a NAV rendszeréhez. Az aktiválást csak egyszer kell végrehajtani egy felhasználó esetén. Az aktiválás során megkapjuk a felhasználóhoz tartozó aláírókulcsot, amelyet el kell tárolni.

A felhasználó aktiválásához a következő lépéseket kell végrehajtani:

1.  **Inaktív felhasználó adatai:** A felhasználói regisztráció során a felhasznló számára az UPO-ról kiküldött, a felhasználó saját tárhelyére érkezet API kulcsból nyerjük ki az adatokat:

    ```php
    $user = $navM2m->getInactiveUser($_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
    ```

2.  **Token igénylése:** Az aktiváláshoz először egy tokent kell igényelni az inaktív felhasználóval:

    ```php
    $token = $navM2m->createToken($user);
    ```

3.  **Felhasználó aktiválása:** Aktiválja a felhasználót, és megkapja a felhasználóhoz tartozó titkos aláírókulcsot:

    ```php
    $response = $navM2m->activateUser($user, $token['accessToken']);
    // TODO el kell tárolni a username ($user['id]), password ($user['password']) és signingKey-t ($response['signatureKey']) az adatbázisban a userhez
    ```

### Bizonylat beadás folyamata

1. Token igénylése a `createToken()` függvény segítségével. Egy token 10 percig érvényes.
2. Fájl feltöltése az `addFile()` függvény segítségével. A következő lépés feltétele, hogy a `result_code` legyen `UPLOAD_SUCCESS` és a `virusScanResultCode` legyen `PASSED`.
   Ha nincs `virusScanResultCode` vagy az értéke`WAITING` , akkor **pár másodperc várakozás után** a fájl státuszát le kell kérdezni a `getFileStatus()` függvény segítségével.
3. Bizonylat létrehozása a `createDocument()` függvény segítségével. A következő lépés feltétele, hogy a `result_code` legyen `CREATE_DOCUMENT_SUCCESS` és a `documentStatus` legyen `VALIDATED`.
   Ha a `documentStatus` értéke `UNDER_PREVALIDATION` vagy `UNDER_VALIDATION`, akkor **pár másodperc várakozás** után a `getDocument()` függvény segítségével le kell kérdezni a bizonylat státuszát.
4. Bizonylat érkeztetése az `updateDocument()` függvény segítségével amely visszatérési értékében tartalmaz egy `arrivalNumber` értéket, amely a beküldés eredményét jelzi.
   Ha a `documentStatus` értéke `UNDER_SUBMIT` akkor **pár másodperc várakozás** után a `getDocument()` függvény segítségével le kell kérdezni a bizonylat státuszát.

#### A bizonylatokkal kapcsolatos kommunikáció DB-ban való tárolásához a következő mezőkre van szükség

- `fileId`: 36 karakter - a bizonylat feltöltésekor megkapott egyedi azonosító
- `correlationId`: 36 karakter - a bizonylat feltöltésekor generált egyedi azonosító
- `status` max 19 karakter - a bizonylat aktuális státusza
  | Kód | Jelentés |
  |-----|----------|
  | UNDER_PREVALIDATION | A bizonylat előellenőrzése folyamatban van. Az előellenőrzés az adózók, a jogosultság és a bizonylat alapvető megfelelőségét vizsgálja. |
  | PREVALIDATION_ERROR | A bizonylat előellenőrzése során hibát talált a rendszer. Ilyen esetben az errors mező nincs kitöltve, mivel az csak a tartalmi hibákat tartalmazza. |
  | UNDER_VALIDATION | A bizonylat tartalmi ellenőrzése folyamatban van. A tartalmi ellenőrzés során a bizonylat megfelelő kitöltését és csatolmányokat vizsgálja a rendszer. |
  | VALIDATION_ERROR | A bizonylat tartalmi ellenőrzése során hibát talált a rendszer. A hibalista.xsd-nek megfelelő formátumú errors mezőben találhatók a hibák. |
  | VALIDATED | A bizonylat megfelelően lett kitöltve, és a csatolmányai is rendben vannak. |
  | UNDER_SUBMIT | A bizonylat beküldése folyamatban van. |
  | SUBMIT_ERROR | A bizonylat beküldése sikertelen. Abban az esetben érdemes ebben az állapotban ismételt beküldést kérni, ha a hivatali kapu nem volt elérhető. |
  | SUBMITTED | A bizonylat sikeresen be lett küldve. |
- `result` max 24 karakter - a legutolsó API hívás eredménye
  | Művelet | Kód | Jelentés |
  |---------|-----|---------|
  | `addFile` | UPLOAD_SUCCESS | Sikeres fájl feltöltés. Nem jelenti azt, hogy a fájl nem vírusos. |
  | `addFile` | HASH_FAILURE | A fájl-ról képzett sha256 hash nem egyezik a paraméterben megadottal. |
  | `addFile` | OTHER_ERROR | Egyéb hiba következett be a feltöltés során. |
  | `createDocument` | CREATE_DOCUMENT_SUCCESS | A bizonylat létrehozása sikeresen megtörtént. A tartalmi validáció elindult. |
  | `createDocument` | UNKNOWN_FILE_ID | A megadott azonosítóval nem található fájl a fájltárolóban. |
  | `createDocument` | FILE_ID_ALREADY_USED | A megadott fájltárolóbeli fájlazonosítóval már létezik bizonylat. |
  | `createDocument` | UNSUCCESSFUL_VALIDATION | A validáció valamilyen hiba miatt nem tudott lefutni. |
  | `createDocument` | INVALID_SENDER | Érvénytelen beküldő. |
  | `createDocument` | INVALID_TAXPAYER | Érvénytelen adózó. |
  | `createDocument` | SENDER_HAS_NO_RIGHT | A beküldőnek nincs jogosultsága a bizonylat beküldésére az adózó nevében. |
  | `createDocument` | INVALID_DOCUMENT_TYPE | A bizonylattípus nem küldhető be. |
  | `createDocument` | INVALID_DOCUMENT_VERSION | A bizonylatverzió nem küldhető be. |
  | `createDocument` | FILE_CONTAINS_VIRUS | A bizonylatfájl, vagy annak csatolmánya vírusos. |
  | `createDocument` | INVALID_SIGNATURE | Érvénytelen aláírás. |
  | `createDocument` | OTHER_ERROR | Egyéb hiba. |
  | `updateDocument` | UPDATE_DOCUMENT_SUCCESS | A bizonylat módosítása sikeresen megtörtént. |
  | `updateDocument` | UNKNOWN_FILE_ID | A megadott azonosítóval nem található fájl a fájltárolóban. |
  | `updateDocument` | STATUS_CHANGE_NOT_ENABLED | A bizonylat aktuális státuszából, a bizonylat módosítás kérésben megadott státuszba nem engedélyezett az átmenet. |
  | `updateDocument` | SUBMIT_ERROR | A bizonylat beküldése sikertelen. |
  | `updateDocument` | TOO_BIG_KR_FILE | A KR fájl mérete meghaladja a beküldési limitet |
  | `updateDocument` | INVALID_SENDER | Érvénytelen beküldő. |
  | `updateDocument` | INVALID_TAXPAYER | Érvénytelen adózó. |
  | `updateDocument` | SENDER_HAS_NO_RIGHT | A beküldőnek nincs jogosultsága a bizonylat beküldésére az adózó nevében. |
  | `updateDocument` | INVALID_DOCUMENT_TYPE | A bizonylattípus nem küldhető be. |
  | `updateDocument` | INVALID_DOCUMENT_VERSION | A bizonylatverzió nem küldhető be. |
  | `updateDocument` | INVALID_SIGNATURE | Érvénytelen aláírás. |
  | `updateDocument` | OTHER_ERROR | Egyéb hiba. |
  | `getDocument` | GET_DOCUMENT_SUCCESS | A bizonylatadatok lekérdezése sikeresen megtörtént. |
  | `getDocument` | UNKNOWN_FILE_ID | A megadott azonosítóval nem található fájl a fájltárolóban. |
  | `getDocument` | OTHER_ERROR | Egyéb hiba |
- `virusScanResultCode`: max 11 karakter - a bizonylat feltöltésekor végrehajtott víruskeresés eredménye
  | Kód | Jelentés |
  |-----|----------|
  | PASSED | Sikeres fájl feltöltés. |
  | FAILED | A fájl-ról képzett sha256 hash nem egyezik a paraméterben megadottal. |
  | WAITING | A vírusellenőrzés még folyamatban van. |
  | OTHER_ERROR | Egyéb hiba következett be a feltöltés során. |
- `arrivalNumber` minimum 27 karakter - a bizonylat érkeztetésekor megkapott egyedi azonosító
- `errors` - a hibákat tartalmazó xml fájl bzip2-vel tömörítve. Az xml a hibalista.xsd-vel dolgozható fel.

### Fájl feltöltése

Bizonylatfájl feltöltése:

```php
$result = $navM2m->addFile(
    file: 'bizonylat/xml/file/eleresi/utja',
    signatureKey: $user['signatureKey'],
    accessToken: $token['accessToken'],
);
$fileId = $result['fileId'];
$correlationId = $result['correlationId'];
echo "Fájl feltöltve. File ID: " . $fileId;
```

A `$result` hibák kezelésére a `minta.php` fájlban találsz példát.

### Bizonylat létrehozása/validálása

A bizonylat létrehozása és validálása:

```php
$result = $navM2m->createDocument(
    fileId: $fileId,
    correlationId: $correlationId,
    signatureKey: $user['signatureKey'],
    accessToken: $token['accessToken']
);
```

A `$result` hibák kezelésére a `minta.php` fájlban találsz példát.

### Bizonylat érkeztetése

A `createDocument` függvény után a bizonylat érkeztetése:

```php
$result = $navM2m->updateDocument(
    fileId: $fileId,
    correlationId: $correlationId,
    signatureKey: $user['signatureKey'],
    accessToken: $token['accessToken']
);
```

A `$result` hibák kezelésére a `minta.php` fájlban találsz példát.

### Loggolás

A log kiírja a standard outputra a kéréseket és a válaszokat, ami hasznos lehet a hibakeresés során.
A loggolást az objektum létrehozásakor be kapcsolhatod, alapértelmezetten ki van kapcsolva.

```php
$navM2m = new NavM2m(mode: 'sandbox', client: $client, logger: true);
```

Lehetőség van saját logger függvény használatára is. Ebben az esetben a log üzenetek nem a standard outputra íródnak ki, hanem a megadott függvény kapja meg őket:

```php
// Példa Monolog használatával
$logger = new Monolog\Logger('nav-m2m');
$logger->pushHandler(new Monolog\Handler\StreamHandler('nav-m2m.log'));

$loggerCallback = function($message) use ($logger) {
    $logger->info($message);
};

$navM2m = new NavM2m(
    mode: 'sandbox',
    client: $client,
    logger: true,
    loggerCallback: $loggerCallback
);
```

```php
// Példa CakePHP Log használatával
$loggerCallback = function($message) {
    \Cake\Log\Log::debug($message, 'nav-m2m');
};

$navM2m = new NavM2m(
    mode: 'sandbox',
    client: $client,
    logger: true,
    loggerCallback: $loggerCallback
);
```

## Támogatás

Ha bármilyen kérdésed vagy problémád van, nyiss egy issue-t.

[NAV M2M repo:](https://github.com/nav-gov-hu/M2M)
