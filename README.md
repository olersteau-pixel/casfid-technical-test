# DailyTrends

## Descripción del proyecto

El objetivo es desarrollar una API en Symfony 7+ que recoja y gestione noticias de portada de El País y El Mundo mediante scraping, y permita gestionarlas (lectura, creación manual,
edición y borrado) vía API REST.

## OpenAPI Specification.

A continuación, adjunto las rutas disponibles :
| Nombre    | Detalle                                                       |   Metodo  |   Path                |
|   -       |   -                                                           |   -       |   -                   |
|   list    | Obtiene un listado de feeds, con los filtros aplicados        |   GET     |   /api//feeds         |
|   show    | Obtiene los detalles de un feed                               |   GET     |   /api//feeds/{id}    |
|   create  | Crea un feed                                                  |   POST    |   /api/feeds          |
|   delete  | Eliminar un feed                                              |   DELETE  |   /api/feeds/{id}     |
|   update  | Actualiza un feed                                             |   PUT     |   /api/feeds/{id}     |
|   scrape  | Scraping web de las ultimas 5 notiicias de el mundo y el pais |   POST    |   /api/feeds/scrape   |

La api via swagger se encuentra disponible en la dirección **http://localhost:8890/api/doc**
La collection de prueba postman esta disponible igualmente en la carpeta **resource**

## Diagrama de arquitectura

El proyecto tiene la siguiente estructura
```
src/
├── Controller/
│       ├── FeedsController.php
│       └── FeedsScraperController.php
├── DTO/
│   └── Feed/
│       ├── CreateFeedDTO.php
│       ├── FeedResponseDTO.php
│       ├── GetFeedsDTO.php
│       ├── ScrappedFeedsDTO.php
│       └── UpdateFeedDTO.php
├── Entity/
│   └── Feed.php
├── Enum/
│   └── FeedSource.php
├── Exception/
│   ├── DuplicateFeedException.php
│   ├── FeedNotFoundException.php
│   └── ValidationException.php
├── Repository/
│   └── FeedsRepository.php
└── Service/
    ├── Scraper
    │   │── ElMundoScraper.php
    │   │── ElPaisScraper.php    
    │   └── ScraperInterface.php    
    ├── FeedsService.php
    └── FeedsScrapperService.php    

tests/
│── Controller/
│   └── FeedsControllerTest.php
│── Repository/
│   └── FeedsRepositoryTest.php
└── Service/
    │── FeedsServiceTest.php
    └── FeedsScrapperServiceTest.php
```

Adjuntamos un diagrama de flujo de los EP de crud

![Diagrama](./resource/diagrama-crud.png)

Adjuntamos un diagrama de flujo del comando y controlador para el scraping

![Diagrama](./resource/diagrama-scraper.png)

## Tecnología utilizada

- PHP (fpm): 8.4
- XDebug: 3.3
- MySQL: 8.0
- Nginx: lastest

## Instrucciones para instalar el entorno
En un primer tiempo, se tiene clonar el proyecto 
```bash
git clone https://github.com/olersteau-pixel/casfid-technical-test.git
```

Acceder a la carpeta del proyecto
```bash
cd casfid-technical-test
```

Es necesario configurar el .env, indicando los datos correctos (UID / UNAME) asi como los datos de base de datos

```php
DATABASE_URL="mysql://root:password@database_casfid_technical_test:3306/casfid_technical_test?serverVersion=8.0.32&charset=utf8mb4"
```

Se usara los comandos del fichero Makefile para que sea lo mas sencillo posible.

Para levantar (y instalarlo si necesario) el proyecto, se debe usar el siguiente comando

```bash
make up
```

Para descargar e instalar los paquete del composer, ese otro comando 

```bash
make install
```

Y finalmente, para crear la base de datos, tablas y los contenidos de ejemplos que se usaran para el funcionamiento de la api, ese tercer comando 

```bash
make init-db
```

## Comando para hacer el scraping

Para lanzar el scaping de el mnundo y el pais, el comando sera el siguiente.

```bash
make scraper
```
## Comando para lanzar los tests

Se tiene que configurar los datos de base de datos en el .env.test 

```php
DATABASE_URL="mysql://root:password@database_casfid_technical_test:3306/casfid_technical_test?serverVersion=8.0.32&charset=utf8mb4"
```

Para lanzar los tests, el comando sera el siguiente.

```bash
make test
```