<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allow "SELECT *"
    |--------------------------------------------------------------------------
    |
    | When false, unrestricted column selection is disabled: the `select`
    | parameter becomes required (at the top level and inside each non-aggregate
    | include), the wildcard "*" is rejected, and the query builder falls back to
    | the model's primary key only when no explicit columns are provided.
    |
    | Defaults to true for backward compatibility.
    |
     */
    'allow_select_all' => true,

    /*
    |--------------------------------------------------------------------------
    | Allow Includes
    |--------------------------------------------------------------------------
    |
    | When false, relation eager-loading is disabled entirely: the `include`
    | parameter is stripped from the parsed input, its validation is skipped, and
    | the query builder never loads relations.
    |
    | Defaults to true for backward compatibility.
    |
     */
    'allow_include' => true,

    /*
    |--------------------------------------------------------------------------
    | Query String Parameters Name
    |--------------------------------------------------------------------------
    |
    | The names of query string parameters from the request to be used for
    | filtering inputs. If not provided, default parameter names will be used.
    |
     */
    'params' => [
        /**
         * The query string parameter to use for the `select`.
         * Default is `select`.
         */
        'select' => null,

        /**
         * The query string parameter to use for the `filter`.
         * Default is `filter`.
         */
        'filter' => null,

        /**
         * The query string parameter to use for the `include`.
         * Default is `include`.
         */
        'include' => null,

        /**
         * The query string parameter to use for the `sort`.
         * Default is `sort`.
         */
        'sort' => null,

        /**
         * The query string parameter to use for the `group`.
         * Default is `group`.
         */
        'group' => null,

        /**
         * The query string parameter to use for the `page`.
         * Default is `page`.
         */
        'page' => null,

        /**
         * The query string parameter to use for the `limit`.
         * Default is `limit`.
         */
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Limit
    |--------------------------------------------------------------------------
    |
    | The number of records to retrieve by default if no limit was provided.
    |
     */
    'limit' => [
        'default' => 15,
        'max' => 50,
    ],
];
