<?php
/**
 * Exemplo de configuração MatchZy para teste
 * Este arquivo mostra o formato correto que o MatchZy espera
 */

header('Content-Type: application/json; charset=utf-8');

// Exemplo baseado na documentação oficial do MatchZy
$exampleConfig = [
    "matchid" => "example_match_001",
    "team1" => [
        "name" => "Astralis",
        "players" => [
            "76561197990682262" => "Xyp9x",
            "76561198010511021" => "gla1ve",
            "76561197979669175" => "K0nfig",
            "76561198028458803" => "BlameF",
            "76561198024248129" => "farlig"
        ]
    ],
    "team2" => [
        "name" => "NaVi",
        "players" => [
            "76561198034202275" => "s1mple",
            "76561198044045107" => "electronic",
            "76561198246607476" => "b1t",
            "76561198121220486" => "Perfecto",
            "76561198040577200" => "sdy"
        ]
    ],
    "num_maps" => 3,
    "maplist" => [
        "de_mirage",
        "de_overpass", 
        "de_inferno"
    ],
    "map_sides" => [
        "team1_ct",
        "team2_ct",
        "knife"
    ],
    "spectators" => [
        "players" => [
            "76561198264582285" => "Anders Blume"
        ]
    ],
    "clinch_series" => true,
    "players_per_team" => 5,
    "cvars" => [
        "hostname" => "MatchZy: Astralis vs NaVi",
        "mp_friendlyfire" => "0",
        "mp_teamname_1" => "Astralis",
        "mp_teamname_2" => "NaVi"
    ]
];

echo json_encode($exampleConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
