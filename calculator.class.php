<?php

class Calculator {
	
	private $basic_points;
	private $extra_points;
	private $total_points;
	
	private $student_data;
	
	private static $min_subject_percent = 20; 	//Minimum pontszám (százalék) kötelező tantárgynál
	private static $schools;					//Input (iskolák, melyeknél vizsgálatot tehet a program) - bővíthető
	private static $req_subjects 		= ["magyar nyelv és irodalom", "történelem", "matematika"]; //A lista bővíthető
	
	public function __construct($data) {
		$this->student_data = $data;
		self::$schools = json_decode($this->getSchools());
	}

	public function calculate() {
		$student_graduation_subjects 		= [];
		$student_graduation_subject_type 	= [];
		$student_graduation_subject_results	= [];
		
		$student_graduation_extra_category	= [];
		$student_graduation_extra_type		= [];
		$student_graduation_extra_lang		= [];
		
		//A diák érettségi tantárgyai és azok eredményei 
		for($i=0; $i<count($this->student_data["erettsegi-eredmenyek"]); $i++) {
			array_push($student_graduation_subjects, $this->student_data["erettsegi-eredmenyek"][$i]["nev"]);
			array_push($student_graduation_subject_type, $this->student_data["erettsegi-eredmenyek"][$i]["tipus"]);
			array_push($student_graduation_subject_results, $this->student_data["erettsegi-eredmenyek"][$i]["eredmeny"]);
		}
		for($i=0; $i<count($this->student_data["tobbletpontok"]); $i++) {
			array_push($student_graduation_extra_category, $this->student_data["tobbletpontok"][$i]["kategoria"]);
			array_push($student_graduation_extra_type, $this->student_data["tobbletpontok"][$i]["tipus"]);
			array_push($student_graduation_extra_lang, $this->student_data["tobbletpontok"][$i]["nyelv"]);
		}
		
		//Létezik az iskola, ahova szeretne jelentkezni?
		foreach (self::$schools as $school) {
			$student_school = $this->selectSchool($school, $this->student_data);
			if($student_school !== false) {				
				break;
			}
		}
		if($student_school == false) {
			return "hiba, nincs ilyen iskola";
		}
		
		//Létezik az összes kötelező érettségi tantárgy? (req_subjects)
		if(!$this->existReqSubjects($student_graduation_subjects)) {
			return "hiba, nem lehetséges a pontszámítás a kötelező érettségi tárgyak hiánya miatt";
		} 
		
		//Megnézi van-e olyan kötelező érettségi tantárgy eredmény, ami 20% alatti (csak a kötelezőket nézi!!!)
		if(!$this->checkMinReq($student_graduation_subjects, $student_graduation_subject_results)) {
			return "hiba, nem lehetséges a pontszámítás a kötelező tárgyból elért ".self::$min_subject_percent."% alatti eredmény miatt";
		}
		
		//Iskolának megfelelően van-e neki kötelezően választható tantárgy?
		if(count(array_intersect($student_school->chosen_subjects, $student_graduation_subjects)) == 0) {
			return "hiba, nincs olyan kötelezően választható tantárgy, amit az iskola megkövetel!";
		}
		
		//Iskolának megfelelően kötelezően választható tárgyak közül elérte-e valamelyik a 20%-ot 
		if(!$this->checkChosenMinReq($student_graduation_subjects, $student_graduation_subject_results, $student_school->chosen_subjects)) {
			return "hiba, iskola számára megkövetelt kötelezően választható tantárgyak közül egyik sem érte el a ".self::$min_subject_percent."%-ot";
		}
		
		//Iskolának megfelelően van-e neki kötelező tantárgy a megfelelő szinten?
		if(!$this->checkReqType($student_graduation_subjects, 
			$student_graduation_subject_type, 
			$student_school->req_subject, 
			$student_school->req_subject_type)) {
			return "hiba, nincs olyan tantárgy vagy nem olyan szinten van, amit az iskola megkövetel!";
		}
		
		//Itt már lehet pontokat számolni
		$this->basic_points = $this->getBasicPointCalc($student_graduation_subjects, $student_graduation_subject_results, $student_school->req_subject, $student_school->chosen_subjects);	
		
		//Többletpontok
		$this->extra_points = $this->getExtraPointCalc($student_graduation_subject_type, $student_graduation_extra_category, $student_graduation_extra_type, $student_graduation_extra_lang);
		
		$this->total_points = $this->basic_points + $this->extra_points;
		
		return "output: ".$this->total_points." (".$this->basic_points." alappont + ".$this->extra_points." többletpont)";
	}
	
	private function checkChosenMinReq($subjects, $results, $chosen_subjects) {
		$chosen_max = 0;
		for($c=0; $c<count($chosen_subjects); $c++) {
			if(in_array($chosen_subjects[$c], $subjects)) {
				$index = array_search($chosen_subjects[$c], $subjects);
				if($this->convertPercent($results[$index]) > $chosen_max) {
					$chosen_max = $this->convertPercent($results[$index]);
				}
			}
		}
		return ($chosen_max < self::$min_subject_percent) ? false : true;
	}
	
	private function checkReqType($subjects, $types, $req_subject, $req_subject_type) {
		for($s=0; $s<count($subjects); $s++) {
			if($subjects[$s] == $req_subject) {
				if($req_subject_type == "*" || $types[$s] == $req_subject_type) {
					return true;
				}
			}
		}
		return false;
	}
	
	private function checkMinReq($subjects, $results) {
		for($s=0; $s<count($subjects); $s++) {
			if(in_array($subjects[$s], self::$req_subjects)) {
				if(intval($this->convertPercent($results[$s])) < self::$min_subject_percent) {
					return false;
					break;
				}
			}
		}
		return true;
	}
	
	private function convertPercent($result) {
		return intval(substr($result, 0, strpos($result, '%')));
	}
	
	private function existReqSubjects($student_data) {
		foreach(self::$req_subjects as $req_subject) {
			if(!in_array($req_subject, $student_data)) {	
				return false;
			}
		}
		return true;
	}
	
	private function selectSchool($school, $student_data) {
		if($school->egyetem != $student_data["valasztott-szak"]["egyetem"]) {
			return false;
		}
		if($school->kar != $student_data["valasztott-szak"]["kar"]) {
			return false;
		}
		if($school->szak != $student_data["valasztott-szak"]["szak"]) {
			return false;
		}
		return $school;
	}
	
	private function getBasicPointCalc($subjects, $results, $req_subject, $chosen_subjects) {
		
		$faq_point 	= 0;
		$req_point	= 0;
		
		for($s=0; $s<count($subjects); $s++) {
			//Kötelezően választottból (amit az iskola megkövetel) a legtöbb pontot elérő tantárgy eredménye
			if(in_array($subjects[$s], $chosen_subjects)) {
				if($this->convertPercent($results[$s]) > $faq_point) {
					$faq_point = $this->convertPercent($results[$s]); 
				}
			}	
			if($subjects[$s] == $req_subject) {
				$req_point = $this->convertPercent($results[$s]);
			}
		}
		return ($faq_point + $req_point) * 2;
	}
	
	private function getExtraPointCalc($subject_types, $extra_cat, $extra_type, $extra_lang) {
		$extra_point 	= 0;
		$languages 		= array();
		
		//Emelt tantárgyak számolása
		$subject_types = array_count_values($subject_types);
		$extra_point += intval(($subject_types["emelt"])) * 50;
		
		//Többlet pontok számolása (TODO: ezt a részt felkészíteni arra, ha nem csak "Nyelvvizsga" kategória és/vagy nem csak "B2", "C1" típusok)
		for($e=0; $e<count($extra_cat); $e++) {
			$language_results 	= array();
			if($extra_cat[$e] == "Nyelvvizsga") {
				$language_results["language"] = $extra_lang[$e];
				if($extra_type[$e] == "B2") {
					$language_results["type_value"] = 28;
				}
				if($extra_type[$e] == "C1") {
					$language_results["type_value"] = 40;
				}
				if(count($languages) > 0) {
					for($l=0; $l<count($languages); $l++) {
						if($languages[$l]["language"] == $extra_lang[$e]) {
							if($languages[$l]["type_value"] < $language_results["type_value"]) {
								$languages[$l]["type_value"] = $language_results["type_value"];
							} 
						} 
					}
					if(!(array_search($language_results["language"], array_column($languages, 'language')) !== false)) {
						$languages[] = $language_results;
					} 
				} else {
					$languages[] = $language_results;
				}
			}
		}
		foreach($languages as $language) {
			$extra_point += $language["type_value"];
		}
		return ($extra_point > 100) ? 100 : $extra_point;
	}	
	
	//Ez jöhetne más adatforrásból (például relációs adatbázisból)
	private function getSchools() {
		$schools = array(
			array(
				"egyetem"=>"ELTE",
				"kar"=>"IK",
				"szak"=>"Programtervező informatikus",
				"req_subject"=>"matematika",
				"req_subject_type"=>"*",
				"chosen_subjects"=>array("biologia", "fizika", "informatika", "kémia")
			),
			array(
				"egyetem"=>"PPKE",
				"kar"=>"BTK",
				"szak"=>"Anglisztika",
				"req_subject"=>"angol",
				"req_subject_type"=>"emelt",
				"chosen_subjects"=>array("francia", "német", "olasz", "orosz", "spanyol", "történelem")
			)
		);
		return json_encode($schools);
	}
	
}

?>