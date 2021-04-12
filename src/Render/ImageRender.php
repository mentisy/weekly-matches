<?php

namespace Avolle\UpcomingMatches\Render;

use Avolle\UpcomingMatches\Match;
use Avolle\UpcomingMatches\Render\Helper\ImageMatchesHelper;
use Avolle\UpcomingMatches\SportConfig;
use Avolle\UpcomingMatches\Themes\Theme;
use Cake\Collection\CollectionInterface;
use Imagick;
use ImagickDraw;
use ImagickPixel;

class ImageRender implements RenderInterface
{
    private CollectionInterface $matchesCollection;
    private SportConfig $sportConfig;
    private Theme $theme;

    private Imagick $imagick;

    private ImagickDraw $teamText;
    private ImagickDraw $sportText;

    const IMAGE_INITIAL_WIDTH = 2000;
    const IMAGE_HEIGHT = 807;

    const TEAM_NAME_FONT_SIZE = 46;
    const SPORT_FONT_SIZE = 30;

    const LOGO_POSITION_X = 50;
    const LOGO_POSITION_Y = 100;

    const MATCH_GRID_X_START = 50;
    const MATCH_GRID_Y_START = 200;
    const MATCH_GRID_Y_END = self::IMAGE_HEIGHT - 170;

    private float $imageWidth = self::IMAGE_INITIAL_WIDTH;

    private float $requiredMatchSizeX = self::MATCH_GRID_X_START;
    private float $requiredMatchSizeY = 0;

    public function __construct(CollectionInterface $matchesCollection, SportConfig $sportConfig)
    {
        $this->matchesCollection = $this->groupByDate($matchesCollection);
        $this->sportConfig = $sportConfig;
    }

    public function render(): void
    {
        $this->init();
        $this->renderMatches();
        $this->renderTeamDetails();
        $this->renderSponsors();
    }

    public function output(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $imagick = $this->imagick;

        require TEMPLATES . 'image.php';
    }

    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
    }

    /**
     * Takes the collection of matches and groups them by date
     *
     * @param \Cake\Collection\CollectionInterface $matchesCollection
     * @return \Cake\Collection\CollectionInterface
     */
    private function groupByDate(CollectionInterface $matchesCollection): CollectionInterface
    {
        return $matchesCollection->groupBy(
            fn(Match $match) => $match->day . strftime(' %d. %B', $match->date->getTimestamp())
        );
    }

    private function init()
    {
        if (!isset($this->theme)) {
            $this->theme = new Theme();
        }
        $this->imagick = new Imagick();
        $this->imagick->newImage($this->imageWidth, self::IMAGE_HEIGHT, $this->theme->backgroundColor, 'png');

        $this->teamText = new ImagickDraw();
        $this->teamText->setFont($this->theme->font);
        $this->teamText->setFontSize(self::TEAM_NAME_FONT_SIZE);
        $this->teamText->setFillColor(new ImagickPixel($this->theme->fontColor));

        $this->sportText = new ImagickDraw();
        $this->sportText->setFont($this->theme->font);
        $this->sportText->setFontSize(self::SPORT_FONT_SIZE);
        $this->sportText->setFillColor(new ImagickPixel($this->theme->fontColor));
    }

    private function renderMatches()
    {
        $matchesHelper = new ImageMatchesHelper(
            $this->matchesCollection,
            $this->theme,
            $this->imageWidth,
            self::IMAGE_HEIGHT,
            self::MATCH_GRID_Y_END - self::MATCH_GRID_Y_START
        );

        $image = $matchesHelper->renderMatches();

        $this->requiredMatchSizeX += $matchesHelper->getRequiredWidth();
        $this->requiredMatchSizeY = $matchesHelper->getRequiredHeight();

        $this->imageWidth = $this->requiredMatchSizeX + 30;

        $this->imagick->cropImage($this->imageWidth, self::IMAGE_HEIGHT, 0, 0);
        $this->imagick->setSize($this->imageWidth, self::IMAGE_HEIGHT);

        $this->imagick->compositeImage($image, Imagick::COMPOSITE_DEFAULT, self::MATCH_GRID_X_START, self::MATCH_GRID_Y_START);
    }

    private function renderTeamDetails()
    {
        $x = self::LOGO_POSITION_X;
        $y = self::LOGO_POSITION_Y;

        $this->imagick->annotateImage($this->teamText, $x, $y, 0, strtoupper($this->sportConfig->teamName));
        $this->imagick->annotateImage($this->sportText, $x, $y + self::TEAM_NAME_FONT_SIZE, 0, $this->sportConfig->renderSubTitle);
        $logo = new Imagick();
        $logo->readImage(RENDERABLES . $this->theme->logo);
        $logo->resizeImage(128, 128, null, 0);

        $logoPositionX = $this->imageWidth - 200;

        if ($this->imageWidth <= 500) {
            $logoPositionX += 30;
        }

        $logoPositionX = max([250, $logoPositionX]); // 250px is min X position to avoid hitting team name
        $this->imagick->compositeImage($logo, Imagick::COMPOSITE_DEFAULT, $logoPositionX, 40);
    }

    private function renderSponsors()
    {
        if ($this->theme->sponsors) {
            $sponsors = new Imagick();
            $sponsors->readImage(RENDERABLES . $this->theme->sponsors);
            $factor = $this->imageWidth / $sponsors->getImageWidth();
            $width = $sponsors->getImageHeight() * $factor;
            $yFromBottom = $this->requiredSponsorSpacingFromBottom($width);
            $sponsors->resizeImage($this->imageWidth, $sponsors->getImageHeight() * $factor, null, 0);
            $this->imagick->compositeImage($sponsors, Imagick::COMPOSITE_DEFAULT, 0, $yFromBottom);
        }
    }

    private function requiredSponsorSpacingFromBottom(float $sponsorHeight): float
    {
        return self::IMAGE_HEIGHT - $sponsorHeight - 20; // 20 is margin from bottom
    }

    public function toFile(string $filename)
    {
        $this->imagick->writeImage($filename);
    }
}
