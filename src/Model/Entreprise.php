<?php

namespace App\Model;

class Entreprise
{
    private string $siren;
    private string $nom_raison_sociale;
    private array $siege;

    public function getSiren(): string
    {
        return $this->siren;
    }

    public function getNomRaisonSociale(): string
    {
        return $this->nom_raison_sociale;
    }
    public function getSiege(): array
    {
        return $this->siege;
    }


    public function setSiren(string $siren): void
    {
        $this->siren = $siren;
    }

    public function setNomRaisonSociale(string $nom_raison_sociale): void
    {
        $this->nom_raison_sociale = $nom_raison_sociale;
    }
    public function setSiege(array $siege): void
    {
        $this->siege = $siege;
    }
}
