# NAV-M2M PHP SDK

Ez a PHP SDK (Software Development Kit) a Nemzeti Adó- és Vámhivatal (NAV) által biztosított M2M (Machine-to-Machine) interfészhez való kapcsolódást segíti. Az SDK lehetővé teszi a gépi kommunikációt a NAV rendszereivel, például bizonylatok beküldéséhez.

Ha a csomag hasznos volt a számodra, akkor nyomj rá a star-ra!

## Telepítés

1.  **Composer használata:** A legkényelmesebb mód az SDK telepítésére a Composer használata:

    ```bash
    composer require rrd108/nav-m2m-php
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

$navM2m = new NavM2m(mode: 'sandbox', client: $client); // tesztkörnyezet
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
    list($token, $signingKey) = $navM2m->activateUser($user, $token['accessToken']);
    list($userName, $userPassword) = explode('-', $_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
    // TODO el kell tárolni a username, password és signingKey-t az adatbázisban a userhez, ezután a `NAV2M2M_USER_TEMPORARY_API_KEY` törölhető
    ```

### Fájl feltöltése

Bizonylatfájl feltöltése:

```php
$file = 'path/to/your/document.xml'; // A bizonylat XML fájl elérési útja
$result = $navM2m->addFile(
    file: './09teszt.xml',
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

A log kiírja a képernyőre a kéréseket és a válaszokat, ami hasznos lehet a hibakeresés során.
A loggolást az objektum létrehozásakor be kapcsolhatod, alapértelmezetten ki van kapcsolva.

```php
$navM2m->logger = true;
```

## Támogatás

Ha bármilyen kérdésed vagy problémád van, nyiss egy issue-t.

[NAV M2M repo:](https://github.com/nav-gov-hu/M2M)
