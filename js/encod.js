function encod() {
	    var reg = document.getElementById("reg").value; // Считываем значение региона
	    var base = document.getElementById("base").value;
	    var ps = document.getElementById("ps").value;
	    var appar =  document.getElementById("appar").value;
	    var kom1 =  document.getElementById("kom").value;
	    var pom1 =  document.getElementById("pom").value;
	    var tipe = document.getElementById("tipe").value;
	    var stoika = document.getElementById("stoika").value;
	    var obnom = document.getElementById("obnom").value;
	    document.getElementById("encod").innerHTML = reg;
	    document.getElementById("encod").innerHTML += '-';
 	if (base != ''){
		document.getElementById("encod").innerHTML += base;
	    document.getElementById("encod").innerHTML += '-';
	}
 	if (ps != ''){
	    document.getElementById("encod").innerHTML += ps;
	    document.getElementById("encod").innerHTML += '-';
 	}
 	
 	if (document.getElementById("appar1").checked){
 		document.getElementById("encod").innerHTML += document.getElementById("appar1").value;
 	 	document.getElementById("encod").innerHTML += appar;
 	}
 	if (document.getElementById("appar2").checked){
 		document.getElementById("encod").innerHTML += document.getElementById("appar2").value;
 	 	document.getElementById("encod").innerHTML += kom1;
 	}
 	if (document.getElementById("appar3").checked){
 	 	document.getElementById("encod").innerHTML += pom1;
 	}
 	if (document.getElementById("appar4").checked){
 	 	document.getElementById("encod").innerHTML += 0;
 	}
  	document.getElementById("encod").innerHTML += '-';
	if (stoika != ''){
	    document.getElementById("encod").innerHTML += stoika;
	    document.getElementById("encod").innerHTML += '-';
 	}
  	document.getElementById("encod").innerHTML += tipe;
  	document.getElementById("encod").innerHTML += obnom;
  	
}
function base1() {
	document.getElementById("ps").value = '';
	encod();
}
function ps1() {
	document.getElementById("base").value = '';
	encod();
}
 