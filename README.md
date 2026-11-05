# Sharp Society Barber

## Autorzy

- Franciszek Cabaj
- Aleksander Somla

## Opis projektu

Sharp Society Barber to internetowy system rezerwacji usług barberskich.

System umożliwia klientom przeglądanie oferty salonu, wybór usługi,
pracownika oraz dostępnego terminu wizyty.

## Role użytkowników

### Klient

- rejestracja i logowanie
- przeglądanie usług
- wybór barbera
- rezerwacja wizyty
- przeglądanie własnych rezerwacji
- anulowanie przyszłych rezerwacji
- edycja profilu

### Pracownik

- Logowanie do panelu pracownika
- podgląd własnych wizyt
- podgląd przyszłych wizyt i historii
- zmiana statusu wizyty
- widzi potrzebne informacje o usłudze i kliencie

### Administrator

- zarządzanie użytkownikami
- zarządzanie pracownikami
- zarządzanie kategoriami i usługami
- zarządzanie dostępnością pracowników
- przeglądanie i zarządzanie rezerwacjami
- podgląd podstawowych statystyk

## Technologie

- PHP
- HTML5
- CSS3
- JavaScript
- MySQL
- Git
- GitHub

## Uruchomienie

Instrukcja uruchomienia zostanie uzupełniona w trakcie realizacji projektu.

## Dane testowe

Baza danych zawiera przykładowe dane testowe:
- 4 kategorie usług
- 8 usług
- 5 użytkowników
- 2 pracowników
- przypisania pracowników do usług
- przykładową dostępność pracowników
- 4 przykładowe rezerwacje z różnymi statusami

## Panel administratora

Zrealizowano następujące funkcjonalności:

- panel administratora z podsumowaniem systemu
- zarządzanie użytkownikami
- dodawanie, edycję i zarządzanie kategoriami usług
- dodawanie, edycję i zarządzanie usługami
- przypisywanie kont pracowników do systemu
- aktywowanie i dezaktywowanie pracowników
- przypisywanie usług do pracowników
- zarządzanie tygodniowym grafikiem dostępności pracowników
- wykrywanie nakładających się godzin pracy
- przeglądanie i filtrowanie rezerwacji
- zmienianie statusów rezerwacji
- kontrolę uprawnień administratora i zabezpieczenie formularzy tokenami CSRF

## Funkcje dodatkowe


Do uruchomienia projektu wymagane są XAMPP (Apache, MySQL) oraz przeglądarka internetowa.

1. Skopiuj katalog projektu do `C:\xampp\htdocs\sharp-society-barber`.
2. Uruchom Apache i MySQL w panelu XAMPP.
3. Otwórz phpMyAdmin pod adresem `http://localhost/phpmyadmin`.
4. Zaimportuj plik `database/database.sql`.
5. Sprawdź dane połączenia z bazą w `config/database.php`.
6. Otwórz aplikację pod adresem `http://localhost/sharp-society-barber/public/`.

**Uwaga:** plik SQL usuwa i tworzy bazę `sharp_society_barber` od nowa. Przed importem należy wykonać kopię istniejących danych.

## Uwagi techniczne

Projekt wykorzystuje PHP, PDO i bazę danych MySQL.

Dostęp do panelu administratora jest ograniczony do użytkowników z rolą `admin`. Operacje modyfikujące dane są wykonywane z wykorzystaniem zapytań przygotowanych oraz walidacji danych wejściowych.