<?php
/* Author: magik_cypress */
/* Co-Author: Mikael CAM */

$labels = array();
$labels['sauserprefs']  = 'Spam';
$labels['sauserprefssettings']  = 'Configuration des Spams';
$labels['spamgeneralsettings']  = 'Configuration principale';
$labels['spamthres']  = 'Seuil du score';
$labels['spamsubject']  = 'Tag sur le sujet';
$labels['spamsubjectblank']  = '(Laissez le champ vide si vous n\'en souhaitez pas)';
$labels['spamlang']  = 'Langue du message';
$labels['enabled']  = 'Activer';
$labels['disabled']  = 'Desactiver';
$labels['headers'] = 'En-têtes du message';
$labels['foldheaders'] = 'Autoriser plusieurs lignes dans les en-têtes';
$labels['spamlevelstars'] = 'Utiliser \'Niveau de Spam\' dans les en-têtes';
$labels['spamlevelchar'] = 'Caractère du niveau de spam';
$labels['spamtests'] = 'Services anti-spam externes';
$labels['userazor1'] = 'Utiliser Razor v1';
$labels['userazor2'] = 'Utiliser Razor v2';
$labels['usepyzor'] = 'Utiliser Pyzor';
$labels['usebayes'] = 'Utiliser les règles de classification type Bayésiennes naïves';
$labels['bayesrules'] = 'Utiliser les règles Bayésiennes';
$labels['bayesautolearn'] = 'Utiliser l\'auto-apprentissage Bayésien';
$labels['usedcc'] = 'Utiliser DCC';
$labels['skiprblchecks'] = 'Utiliser Realtime Blackhole List';
$labels['spamreportsettings']  = 'Rapport de configuration';
$labels['spamreport0']  = 'Inclure le rapport dans les en-têtes du message original';
$labels['spamreport1']  = 'Inclure le message original en tant qu\'attachement MIME du rapport';
$labels['spamreport2']  = 'Inclure juste le texte du message original dans le rapport';
$labels['spamaddressrules']  = 'Règles des adresses';
$labels['whitelist_from']  = 'Accepter les messages de';
$labels['blacklist_from']  = 'Rejeter les messages de';
$labels['whitelist_to']  = 'Accepter les messages envoyés à';
$labels['addrule']  = 'Ajouter';
$labels['rule']  = 'Règle';
$labels['bayes'] = 'Paramètres Bayésiens';
$labels['purgebayes'] = 'Effacer les données Bayésiennes personnelles';
$labels['deleteall'] = 'Tout Effacer';

$messages = array();
$messages['sauserprefchanged'] = 'Succes : modification de la configuration des spams';
$messages['sauserpreffailed'] = 'Erreur: aucun changement de la configuration des spams';
$messages['spamthresexp'] = 'Tout message dont le score est au-dessus du seuil est marqué comme spam. L\'augmentation de ce seuil va augmenter la quantité de spams manqués, mais réduire le risque de faux positifs.';
$messages['spamlangexp'] = 'Sélection des langues : Tous les messages reçus qui sont écrits dans des langues non sélectionnées seront traités comme spams possibles.';
$messages['headersexp'] = 'SpamAssassin ajoute des en-têtes aux messages traités fournissant les résultats des contrôles. Utilisez les options ci-dessous pour configurer ces en-têtes :';
$messages['spamtestssexp'] = 'Certains contrôles utilisent des services disponibles sur Internet pour aider à identifier les spams. Sélectionnez ceux que vous souhaitez utiliser :';
$messages['spamreport']  = 'Quand un message est identifié comme spam, un rapport de tous les tests effectués avec leurs résultats est créé... ';
$messages['autowhitelist'] = 'Les adresses e-mail dans votre carnet d\'adresses sont automatiquement ajoutees a la liste \'accepter le courrier provenant de\'.';
$messages['whitelistexp'] = 'Règles de liste blanche et de liste noire : Un astérisque (*) peut être utilisé comme un joker pour une plus grande flexibilité. Par exemple: *@example.com ou *@*.example.com.';
$messages['spamaddressexists'] = 'Une regle existe deja pour cette adresse';
$messages['spamenteraddress'] = 'S\'il vous plait entrer une adresse a ajouter';
$messages['spamaddresserror'] = 'Erreur: L\'adresse semble invalide';
$messages['spamaddressdelete'] = 'Etes vous sur de vouloir supprimer cette regle ?';
$messages['spamaddressdeleteall'] = 'Etes vous sur de vouloir supprimer toutes les regles ?';
$messages['noaddressrules'] = 'Pas de règle trouvée.';
$messages['bayeshelp'] = 'Le système de classification Bayésien essaie d\'identifier le spam en analysant des mots ou des séquences courtes de caractères trouvés généralement dans des messages considérés comme spams ou non.';
$messages['bayesnonspamexp'] = 'Les messages dont le score est en-dessous de ce seuil seront utilisés pour améliorer les systèmes de détection des messages normaux.';
$messages['bayesspamexp'] = 'Les messages dont le score est au-dessus de ce plafond seront utilisés pour améliorer les systèmes de détection des spams.';
$messages['raz1help'] = 'Razor de Vipul est un système en réseau de détection et de filtrage des spams, collaboratif et distribué, basé sur les contributions en spams des utilisateurs. La détection est faite avec des signatures qui déterminent efficacement les contenus spam qui peuvent muter. Les rapports de spam sont validés en prenant en compte la réputation de chaque rapporteur. La version 1 est remplacée par la version 2 et n\'est plus supportée.';
$messages['raz2help'] = 'Razor de Vipul est un système en réseau de détection et de filtrage des spams, collaboratif et distribué, basé sur les contributions en spams des utilisateurs. La détection est faite avec des signatures qui déterminent efficacement les contenus spam qui peuvent muter. Les rapports de spam sont validés en prenant en compte la réputation de chaque rapporteur.';
$messages['pyzhelp'] = 'Pyzor est un système collaboratif en réseau pour détecter et bloquer les spams en utilisant des extraits de messages suffisants pour les identifier. Les extraits sont générés de différentes manières pour permettre de détecter des messages ayant des petites différences.';
$messages['rblhelp'] = 'Les adresses de serveurs trouvées dans les liens hypertexte d\'un message seront contrôlées en utilisant quelques blacklists et listes de sources de spams connues.';
$messages['dcchelp'] = 'Le DCC ou Distributed Checksum Clearinghouse est un système composé de serveurs collectant et comptant les checksums ou signatures de millions de messages chaque jour. Plus grande est la similarité de la signature d\'un message reçu plus il y a de probabilité que ce soit un spam.';
$messages['bayesruleshlp'] = 'Avant que le système Bayésien de classification puisse être utilisé de manière efficace il doit passer par une période d\'apprentissage pour détecter proprement les messages considérés comme spams ou non. Désactiver les règles Bayésiennes n\'empêche pas le système de continuer d\'apprendre automatiquement ou manuellement des message reçus mais il ne sera pas utilisé pour détecter le spam.';
$messages['bayesautohelp'] = 'Quand l\'auto-apprentissage Bayésien est activé le système traite automatiquement les messages qui sont très probablement des spams ou non et utilise l\'information collectée pour améliorer les filtres de détection.';
$messages['foldhelp'] = 'Les en-têtes de SpamAssassin doivent-ils être indentés ? En d\'autres termes doivent-ils être coupés en plusieurs lignes au lieu d\'une très longue ?';
$messages['levelhelp'] = 'L\'en-tête \'Niveau de Spam\' peut être ajouté  au message pour indiquer son score en répétant le caractère spécifié à hauteur du score. Ceci peut être utile pour les éventuels traitements additionnels à effectuer sur le  message.';
$messages['purgebayesconfirm'] = 'Etes vous certain(e) de vouloir effacer toutes vos donnees Bayesiennes ?';
$messages['purgebayesexp'] = 'Efface les données Bayésiennes personnelles collectées à partir de vos messages.';


?>