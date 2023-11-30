<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;


class Entreprise
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 9, max: 9)]
    private string $siren;
    #[Assert\NotBlank]
    private string $nom_raison_sociale;
    #[Assert\NotBlank]
    private array $siege;

    public function getSiren(): string
    {
        return $this->siren;
    }

    public function getNomRaisonSociale(): ?string
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

    public function setNomRaisonSociale(?string $nom_raison_sociale): void
    {
        $this->nom_raison_sociale = $nom_raison_sociale;
    }
    public function setSiege(array $siege): void
    {
        $this->siege = $siege;
    }
}
