#!/bin/bash

#generate sorted list of wikipedia terms
cat relevant_articles.txt | sed 's/_/ /g' | sort > wikipedia_articles_sorted_tmp.txt
#generate sorted list of mesh terms
cat mshd2015.txt | tail -n+5 | sort > mesh_descriptors_sorted_tmp.txt
#generate list of new terms
comm -23 --check-order mesh_descriptors_sorted_tmp.txt wikipedia_articles_sorted_tmp.txt > new_medical_terms.txt
#delete tmp files
rm wikipedia_articles_sorted_tmp.txt
rm mesh_descriptors_sorted_tmp.txt