<?php

namespace App\Enums;

enum UserRole: string
{
    case SiteAdmin = 'site_admin';

    case RegionalManager = 'relationship_manager';

    case CompetentAuthority = 'competent_authority';

    case Discom = 'discom';

    case Circle = 'circle';

    case Division = 'division';

    case Subdivision = 'subdivision';

    case Section = 'section';
}
