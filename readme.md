# Sementes - Sistema de Cestas Agroecológicas e de Grupos de Consumo Responsável

Woocommerce adaptation for Responsible Consumer Groups.

## Description

This plugin offers some enhancements to the Woocommerce e-commerce platform to make it fit for using in Responsible Consumer Groups (GCR). It assumes GCRs are composed by several consumers and several suppliers, and the there is a buying cycle.

Some of the features are:

* Register a cycle, with a opening and closing time. During this period, consumers can order their products;
* Register several delivery places;
* Several reports are ofered per cycle: (i) Detailed orders; (ii) Detailed orders per supplier; (iii) Summary (total number of products) per supplier; (iv) Detailed orders per delivery place; (v) Detailed orders per delivery place and supplier;
* Reports are shown in HTML and can be exported to XLSX and PDF;

## Usage manual
https://eita.coop.br/wp-content/uploads/2023/02/Manual-Basico-Uso-Plugin-Sementes.pdf

## Installation

Prerequisites:
* PHP 7.2+
* Composer
* NPM

Once you've installed all of the prerequisites, you can run the following command on your 'wp-plugins' folder:

* git clone https://gitlab.com/eita/sementes.git 

And then run the following commands on 'sementes' folder to get everything working:

* composer install

'composer install' will install the project dependencies from composer.lock

* npm install

'npm install' will install all modules listed as dependencies in package.json

* gulp

'gulp' will compile all scss from gulpfile.js

