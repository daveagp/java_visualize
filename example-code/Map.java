import java.util.*;

public class Map {
   private static interface Mapper<I,O> {
      public O apply(I i);
   }
   private static <I,O> List<O> map(Mapper<I,O> m, List<I> list) {
      List<O> results = new ArrayList<O>();
      for ( I i : list ) {
         results.add(m.apply(i));
      }
      return results;
   }
   public static void main(String[] args) {
      List<Integer> ints = Arrays.asList(new Integer[] {2, 3, 5});
      Mapper<Integer, String> transformer =
         // more than meets the eye
         new Mapper<Integer, String>() {
         public String apply(Integer s) {
            return ""+s+s+s;
         }
      }; 
      System.out.println(map(transformer, ints));
   }
}